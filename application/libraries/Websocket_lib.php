<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Redis\RedisSubscriber;
use Amp\Redis\RedisConfig;
use Amp\Socket;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\WebsocketClient;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use function Amp\trapSignal;
use function Amp\ByteStream\getStdout;
use function Amp\Redis\createRedisConnector;


/** 
 * Add the following packages to composer:
 * "amphp/websocket-server": "^4.0",
 * "amphp/log": "^2.0",
 * "amphp/redis": "^2.0"
 */
class Websocket_lib
{
	/** @var WebsocketClientGateway[] */
	private $gateways = []; // channel => gateway

	/** @var LoggerInterface */
	private $logger;

	/** @var SocketHttpServer */
	private $server;

	/** @var String */
	private $channelPrefix;

	/** @var Array */
	private $config;

	private const REDIS_RECONNECT_DELAY = 1; // seconds
	private const MAX_RECONNECT_ATTEMPTS = 5;
	private const BACKOFF_MULTIPLIER = 2;

	public function __construct()
	{
		$_ci = &get_instance();
		$_ci->config->load('lib_websocket', TRUE, TRUE);
		$this->config = $_ci->config->item('lib_websocket');
	}

	public function generateLink($user_identifier = null, $channel = null)
	{
		$jwtAudience = $this->config['jwt_audience'] ?? 'websocket';
		$baseURL = $this->config['url'] ?? '';

		$channels = ['channels' => [$channel]];

		$_ci = &get_instance();
		$_ci->load->library('jwt_lib');
		$token = $_ci->jwt_lib->generate_token($user_identifier, $jwtAudience, ['user'], $channels);

		return "{$baseURL}?channel={$channel}&token={$token}";
	}

	/**
	 * Start WebSocket server
	 * Usage: ./bin/cli_run.sh manager websockets serve
	 */
	public function serve()
	{
		echo "Starting WebSocket server...\n";

		try {
			$logLevelName = $this->config['log_level'] ?? 'Notice';
			$level = \Monolog\Level::fromName($logLevelName);
		} catch (\ValueError $e) {
			$level = \Monolog\Level::Notice;
			log_message('warning', "Invalid log level: {$logLevelName}, defaulting to Notice");
		}


		// Setup logging
		$logHandler = new StreamHandler(getStdout());
		$logHandler->setFormatter(new ConsoleFormatter());
		$logHandler->setLevel($level);
		$this->logger = new Logger('websocket-server');
		$this->logger->pushHandler($logHandler);

		// Create HTTP server with custom
		$maxConnectionsPerIp = $this->config['max_connections_per_ip'] ?? 100;
		$maxConnections = $this->config['max_connections'] ?? 1000;

		$this->server = SocketHttpServer::createForDirectAccess(
			$this->logger,
			enableCompression: true,
			connectionLimit: $maxConnections,
			connectionLimitPerIp: $maxConnectionsPerIp
		);

		$host = $this->config['host'] ?? '0.0.0.0';
		$port = $this->config['port'] ?? 9000;



		$this->server->expose(new Socket\InternetAddress($host, $port));


		$errorHandler = new DefaultErrorHandler();

		// Create client handler
		$jwt_audience = $this->config['jwt_audience'] ?? 'websocket';
		$clientHandler = new WebsocketsClientHandler($this->gateways, $this->logger, $jwt_audience);

		// Create WebSocket endpoint
		$websocket = new Websocket(
			$this->server,
			$this->logger,
			new \Amp\Websocket\Server\Rfc6455Acceptor(),
			$clientHandler
		);

		// Start server
		$this->server->start($websocket, $errorHandler);

		$this->logger->info("WebSocket server started on {$host}:{$port}");

		// Start Redis psubscribe loop in background
		$this->start_redis_psubscribe();

		// Wait for shutdown signal
		$signal = trapSignal([SIGINT, SIGTERM]);
		$this->logger->notice("Received signal {$signal}, stopping server");

		// Cleanup
		$this->cleanup();
	}

	/**
	 * Start async Redis psubscribe loop with exponential backoff
	 */
	private function start_redis_psubscribe(): void
	{
		$gateways = &$this->gateways;
		$logger = $this->logger;

		$_ci = &get_instance();
		$_ci->load->config('redis', TRUE, TRUE);

		// Build Redis URI
		$config = $_ci->config->item('redis');
		$redisHost = $config['host'] ?? '127.0.0.1';
		$redisPort = $config['port'] ?? 6379;
		$redisPassword = $config['password'] ?? null;

		$this->channelPrefix = $config['channel_prefix'] ?? '';

		$redisUri = "tcp://{$redisHost}:{$redisPort}";
		if ($redisPassword) {
			$redisUri = "tcp://:{$redisPassword}@{$redisHost}:{$redisPort}";
		}

		$redisUri = "tcp://{$redisHost}:{$redisPort}";
		$redisConfig = RedisConfig::fromUri($redisUri);

		if ($redisPassword) {
			$redisConfig = $redisConfig->withPassword($redisPassword);
		}

		\Amp\async(function () use (&$gateways, $logger, $redisConfig) {
			$logger->info("Starting Redis psubscribe subscriber...");

			$reconnectAttempts = 0;
			$reconnectDelay = self::REDIS_RECONNECT_DELAY;

			// @phpstan-ignore-next-line
			while (true) {
				$subscriber = null;

				try {
					$logger->info("Creating Redis subscriber for URI: {$redisConfig->getConnectUri()}");

					// Create connector using helper function and then subscriber
					$connector = createRedisConnector($redisConfig);
					$subscriber = new RedisSubscriber($connector);

					$pattern = $this->channelPrefix . '*';
					$logger->info("Subscribing to pattern: {$pattern}");

					// Subscribe to all channels with pattern
					$subscription = $subscriber->subscribeToPattern($pattern);

					$logger->info("Redis psubscribe active, listening for messages...");

					// Reset reconnect attempts on successful connection
					$reconnectAttempts = 0;
					$reconnectDelay = self::REDIS_RECONNECT_DELAY;

					// Iterate over messages as they arrive
					foreach ($subscription as $message) {
						$this->processRedisMessage($message, $gateways, $logger);
					}

					// If subscription ends unexpectedly
					$logger->warning("Redis subscription ended unexpectedly; restarting...");
				} catch (\Throwable $e) {
					$logger->error("Redis psubscribe error: " . $e->getMessage());

					$reconnectAttempts++;

					if ($reconnectAttempts >= self::MAX_RECONNECT_ATTEMPTS) {
						$logger->critical("Max Redis reconnection attempts reached. Resetting counter.");
						$reconnectAttempts = 0;
						$reconnectDelay = self::REDIS_RECONNECT_DELAY * self::MAX_RECONNECT_ATTEMPTS;
					} else {
						// Exponential backoff
						$reconnectDelay = min(
							self::REDIS_RECONNECT_DELAY * pow(self::BACKOFF_MULTIPLIER, $reconnectAttempts - 1),
							60 // Max 60 seconds
						);
					}

					$logger->info("Reconnecting in {$reconnectDelay}s (attempt {$reconnectAttempts})...");
				} finally {
					// Cleanup subscriber if it exists
					$subscriber = null;
				}

				\Amp\delay($reconnectDelay);
			}
		})->ignore();
	}

	/**
	 * Process a Redis pub/sub message
	 */
	private function processRedisMessage(mixed $message, array &$gateways, LoggerInterface $logger): void
	{
		// Message format: [payload, channel]
		if (!is_array($message) || count($message) !== 2) {
			$logger->warning("Invalid message format received from Redis", [
				'message_type' => gettype($message),
				'message' => json_encode($message)
			]);
			return;
		}

		[$payload, $channel] = $message;

		if (strpos($channel, $this->channelPrefix) === 0) {
			$channel = substr($channel, strlen($this->channelPrefix));
		}

		$logger->info("Redis message received", [
			'channel' => $channel,
			'payload_length' => strlen($payload)
		]);

		if (isset($gateways[$channel])) {
			try {
				$gateways[$channel]->broadcastText($payload);
				$logger->info("Broadcasted to WebSocket channel", ['channel' => $channel]);
			} catch (\Throwable $e) {
				$logger->error("Broadcast error for channel {$channel}: " . $e->getMessage(), [
					'exception' => get_class($e),
					'trace' => $e->getTraceAsString()
				]);
			}
		} else {
			$logger->debug("No gateway for {$channel} (message ignored).");
		}
	}

	/**
	 * Cleanup resources on shutdown
	 */
	private function cleanup(): void
	{
		$this->logger->info("Cleaning up resources...");

		try {
			$this->gateways = [];

			// Stop server
			$this->server->stop();

			$this->logger->info("Cleanup complete");
		} catch (\Throwable $e) {
			$this->logger->error("Error during cleanup: " . $e->getMessage());
		}
	}
}


/**
 * Websocket Client Handler
 * 
 * Handles websocket client connections and channels.
 * Extracted from anonymous class for better performance and reusability.
 */
class WebsocketsClientHandler implements WebsocketClientHandler
{
	private array $gateways;
	private LoggerInterface $logger;
	private string $jwt_audience;
	private array $clientChannels = [];

	public function __construct(array &$gateways, LoggerInterface $logger, string $jwt_audience)
	{
		$this->gateways = &$gateways;
		$this->logger = $logger;
		$this->jwt_audience = $jwt_audience;
	}

	public function handleClient(
		WebsocketClient $client,
		Request $request,
		Response $response,
	): void {
		$clientId = $client->getId();
		$this->logger->info("Client connected", ['client_id' => $clientId]);

		try {
			// Parse channel from query string
			$query = $request->getUri()->getQuery();
			parse_str($query, $params);

			// Check if channel is provided
			if (empty($params['channel'])) {
				$this->logger->warning("Client connected without channel parameter", [
					'client_id' => $clientId
				]);

				// Send error message before closing
				$this->sendMessage($client, [
					'type' => 'error',
					'message' => 'Channel parameter is required',
					'code' => 'MISSING_CHANNEL'
				]);

				// Close the connection
				$client->close();
				return;
			}

			$channel = $this->sanitizeChannelName($params['channel']);

			$token = $params['token'] ?? '';
			if (!$this->validateToken($token, $channel)) {
				$this->sendMessage($client, [
					'type' => 'error',
					'message' => 'Invalid or expired token',
					'code' => 'INVALID_TOKEN'
				]);
				$client->close();
				return;
			}

			// Check if sanitization resulted in empty channel
			if (empty($channel)) {
				$this->logger->warning("Invalid channel name provided", [
					'client_id' => $clientId,
					'original_channel' => $params['channel']
				]);

				$this->sendMessage($client, [
					'type' => 'error',
					'message' => 'Invalid channel name. Use only alphanumeric, hyphens, and underscores.',
					'code' => 'INVALID_CHANNEL'
				]);

				$client->close();
				return;
			}

			// Create gateway dynamically if missing
			if (!isset($this->gateways[$channel])) {
				$this->gateways[$channel] = new WebsocketClientGateway();
				$this->logger->info("Created gateway for dynamic channel: {$channel}");
			}

			// Add client to gateway
			$this->gateways[$channel]->addClient($client);
			$this->clientChannels[$clientId] = $channel;

			$this->logger->info("Client subscribed to channel", [
				'client_id' => $clientId,
				'channel' => $channel
			]);

			// Send welcome message immediately
			$this->sendMessage($client, [
				'type' => 'welcome',
				'channel' => $channel,
				'message' => 'Connected successfully',
				'timestamp' => time()
			]);

			// Listen for client messages
			foreach ($client as $message) {
				$this->handleClientMessage($client, $message->buffer());
			}
		} catch (\Throwable $e) {
			$this->logger->error("Error handling client {$clientId}: " . $e->getMessage());
		} finally {
			// Cleanup on disconnect
			$this->cleanupClient($clientId);
		}
	}

	/**
	 * Handle incoming message from client
	 */
	private function handleClientMessage(WebsocketClient $client, string $payload): void
	{
		$clientId = $client->getId();
		$this->logger->debug("Received from client {$clientId}: {$payload}");

		try {
			$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

			if (isset($data['action'])) {
				$this->handleClientAction($client, $data);
			}
		} catch (\JsonException $e) {
			$this->logger->warning("Invalid JSON from client {$clientId}: " . $e->getMessage());
			$this->sendMessage($client, [
				'type' => 'error',
				'message' => 'Invalid JSON format'
			]);
		}
	}

	/**
	 * Handle client actions
	 */
	private function handleClientAction(WebsocketClient $client, array $data): void
	{
		switch ($data['action'] ?? '') {
			case 'ping':
				$this->sendMessage($client, [
					'type' => 'pong',
					'timestamp' => time()
				]);
				break;

			default:
				$this->logger->debug("Unknown action from client: " . ($data['action'] ?? 'none'));
		}
	}

	/**
	 * Send JSON message to client
	 */
	private function sendMessage(WebsocketClient $client, array $data): void
	{
		try {
			$client->sendText(json_encode($data, JSON_THROW_ON_ERROR));
		} catch (\Throwable $e) {
			$this->logger->error("Failed to send message to client: " . $e->getMessage());
		}
	}

	/**
	 * Sanitize channel name to prevent injection
	 */
	private function sanitizeChannelName(string $channel): string
	{
		// Allow only alphanumeric, hyphens, and underscores
		return preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
	}

	/**
	 * Validate JWT channel in token
	 */
	private function validateToken(string $token, string $channel): bool
	{
		if ($token == '') {
			return false;
		}

		$_ci = &get_instance();
		$_ci->load->library('jwt_lib');

		try {
			$payload = $_ci->jwt_lib->decode_token($token, $this->jwt_audience);
			if (!empty($payload['channels']) && is_array($payload['channels'])) {
				return in_array($channel, $payload['channels'], true);
			}

			return false;
		} catch (\Exception $e) {
			$this->logger->warning("Token validation failed: " . $e->getMessage());
			return false;
		}
	}
	/**
	 * Cleanup client resources
	 */
	private function cleanupClient(int $clientId): void
	{
		if (isset($this->clientChannels[$clientId])) {
			$this->logger->info("Client {$clientId} disconnected from channel: {$this->clientChannels[$clientId]}");
			unset($this->clientChannels[$clientId]);
		}
	}
}
