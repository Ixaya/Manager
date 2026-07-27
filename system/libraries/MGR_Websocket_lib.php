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

final class MGR_Websocket_channel_regex
{
	public const ALLOWED = '/^[a-zA-Z0-9_\-:|]+$/';
	public const SANITIZE = '/[^a-zA-Z0-9_\-:|]/';
}

/**
 * Add the following packages to composer:
 * "amphp/websocket-server": "^4.0",
 * "amphp/log": "^2.0",
 * "amphp/redis": "^2.0"
 */

class MGR_Websocket_lib
{
	/** @var WebsocketClientGateway[] */
	protected array $gateways = []; // channel => gateway

	protected \Monolog\Logger $logger;

	protected SocketHttpServer $server;

	protected string $channel_prefix;

	protected array $config;

	protected const REDIS_RECONNECT_DELAY = 1; // seconds
	protected const MAX_RECONNECT_ATTEMPTS = 5;
	protected const BACKOFF_MULTIPLIER = 2;

	public function __construct()
	{
		$_ci = &get_instance();
		$_ci->config->load('lib_websocket', true, true);
		$this->config = $_ci->config->item('lib_websocket');
	}

	/**
	 * Generate a WebSocket connection link with an embedded JWT for the given channel
	 *
	 * @param int|string|null $user_identifier User database ID to embed in the token
	 * @param ?string $channel Channel name the token grants access to
	 * @return ?string Full WebSocket URL with channel and token query parameters
	 */
	public function generateLink(int|string|null $user_identifier = null, ?string $channel = null): ?string
	{
		if ($channel === null || $channel === '') {
			return null;
		}

		$channel = preg_replace(MGR_Websocket_channel_regex::SANITIZE, '', $channel);

		$jwt_audience = $this->config['jwt_audience'] ?? 'websocket';
		$base_url = $this->config['url'] ?? '';

		$channels = ['channels' => [$channel]];

		$_ci = &get_instance();
		$_ci->load->library('jwt_lib');
		$token = $_ci->jwt_lib->generate_token(user_id: $user_identifier, aud: $jwt_audience, extra: $channels);

		$channel_encoded = urlencode($channel);

		return "{$base_url}?channel={$channel_encoded}&token={$token}";
	}

	/**
	 * Start WebSocket server
	 * Usage: ./bin/cli_run.sh manager websockets serve
	 */
	public function serve(): void
	{
		echo "Starting WebSocket server...\n";

		$log_level_name = $this->config['log_level'] ?? 'Notice';
		try {
			$level = \Monolog\Level::fromName($log_level_name);
		} catch (\ValueError $e) {
			$level = \Monolog\Level::Notice;
			log_message('warning', "Invalid log level: {$log_level_name}, defaulting to Notice");
		}


		// Setup logging
		$log_handler = new StreamHandler(getStdout());
		$log_handler->setFormatter(new ConsoleFormatter());
		$log_handler->setLevel($level);
		$this->logger = new Logger('websocket-server');
		$this->logger->pushHandler($log_handler);

		// Create HTTP server with custom
		$max_connections_per_ip = $this->config['max_connections_per_ip'] ?? 100;
		$max_connections = $this->config['max_connections'] ?? 1000;

		$this->server = SocketHttpServer::createForDirectAccess(
			logger: $this->logger,
			enableCompression: true,
			connectionLimit: $max_connections,
			connectionLimitPerIp: $max_connections_per_ip
		);

		$host = $this->config['host'] ?? '0.0.0.0';
		$port = $this->config['port'] ?? 9000;



		$this->server->expose(new Socket\InternetAddress($host, $port));


		$error_handler = new DefaultErrorHandler();

		// Create client handler
		$jwt_audience = $this->config['jwt_audience'] ?? 'websocket';
		$client_handler = new MGRWebsocketsClientHandler(
			gateways: $this->gateways,
			logger: $this->logger,
			jwt_audience: $jwt_audience,
			max_connections: $max_connections
		);

		// Create WebSocket endpoint
		$websocket = new Websocket(
			httpServer: $this->server,
			logger: $this->logger,
			acceptor: new \Amp\Websocket\Server\Rfc6455Acceptor(),
			clientHandler: $client_handler
		);

		// Start server
		$this->server->start($websocket, $error_handler);

		$this->logger->info("WebSocket server started on {$host}:{$port}");

		// Start Redis psubscribe loop in background
		$this->startRedisPsubscribe();

		// Wait for shutdown signal
		$signal = trapSignal([SIGINT, SIGTERM]);
		$this->logger->notice("Received signal {$signal}, stopping server");

		// Cleanup
		$this->cleanup();
	}

	/**
	 * Start async Redis psubscribe loop with exponential backoff
	 */
	protected function startRedisPsubscribe(): void
	{
		$gateways = &$this->gateways;
		$logger = $this->logger;

		$_ci = &get_instance();
		$_ci->load->config('redis', true, true);

		// Build Redis URI
		$config = $_ci->config->item('redis');
		$redis_host = $config['host'] ?? '127.0.0.1';
		$redis_port = $config['port'] ?? 6379;
		$redis_password = $config['password'] ?? null;

		$this->channel_prefix = $config['channel_prefix'] ?? '';

		$redis_uri = "tcp://{$redis_host}:{$redis_port}";
		$redis_config = RedisConfig::fromUri($redis_uri);

		if ($redis_password) {
			$redis_config = $redis_config->withPassword($redis_password);
		}

		\Amp\async(function () use (&$gateways, $logger, $redis_config) {
			$logger->info("Starting Redis psubscribe subscriber...");

			$reconnect_attempts = 0;
			$reconnect_delay = self::REDIS_RECONNECT_DELAY;

			// @phpstan-ignore-next-line
			while (true) {
				$subscriber = null;

				try {
					$logger->info("Creating Redis subscriber for URI: {$redis_config->getConnectUri()}");

					// Create connector using helper function and then subscriber
					$connector = createRedisConnector($redis_config);
					$subscriber = new RedisSubscriber($connector);

					$pattern = $this->channel_prefix . '*';
					$logger->info("Subscribing to pattern: {$pattern}");

					// Subscribe to all channels with pattern
					$subscription = $subscriber->subscribeToPattern($pattern);

					$logger->info("Redis psubscribe active, listening for messages...");

					// Reset reconnect attempts on successful connection
					$reconnect_attempts = 0;
					$reconnect_delay = self::REDIS_RECONNECT_DELAY;

					// Iterate over messages as they arrive
					foreach ($subscription as $message) {
						$this->processRedisMessage(message: $message, gateways: $gateways, logger: $logger);
					}

					// If subscription ends unexpectedly
					$logger->warning("Redis subscription ended unexpectedly; restarting...");
				} catch (\Throwable $e) {
					$logger->error("Redis psubscribe error: " . $e->getMessage());

					$reconnect_attempts++;

					if ($reconnect_attempts >= self::MAX_RECONNECT_ATTEMPTS) {
						$logger->critical("Max Redis reconnection attempts reached. Resetting counter.");
						$reconnect_attempts = 0;
						$reconnect_delay = self::REDIS_RECONNECT_DELAY * self::MAX_RECONNECT_ATTEMPTS;
					} else {
						// Exponential backoff
						$reconnect_delay = min(
							self::REDIS_RECONNECT_DELAY * pow(self::BACKOFF_MULTIPLIER, $reconnect_attempts - 1),
							60 // Max 60 seconds
						);
					}

					$logger->info("Reconnecting in {$reconnect_delay}s (attempt {$reconnect_attempts})...");
				} finally {
					// Cleanup subscriber if it exists
					$subscriber = null;
				}

				\Amp\delay($reconnect_delay);
			}
		})->ignore();
	}

	/**
	 * Process a Redis pub/sub message
	 */
	protected function processRedisMessage(mixed $message, array &$gateways, LoggerInterface $logger): void
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

		if (strpos($channel, $this->channel_prefix) === 0) {
			$channel = substr($channel, strlen($this->channel_prefix));
		}

		$logger->debug("Redis message received", [
			'channel' => $channel,
			'payload_length' => strlen($payload)
		]);

		if (isset($gateways[$channel])) {
			try {
				$gateways[$channel]->broadcastText($payload);
				$logger->debug("Broadcasted to WebSocket channel", ['channel' => $channel]);
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
	protected function cleanup(): void
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
 */
class MGRWebsocketsClientHandler implements WebsocketClientHandler
{
	protected array $gateways;
	protected \Monolog\Logger $logger;
	protected string $jwt_audience;
	protected int $max_connections;
	protected array $client_channels = [];
	protected int $active_connections = 0;

	public function __construct(array &$gateways, \Monolog\Logger $logger, string $jwt_audience, int $max_connections)
	{
		$this->gateways = &$gateways;
		$this->logger = $logger;
		$this->jwt_audience = $jwt_audience;
		$this->max_connections = $max_connections;
	}

	/**
	 * Accept a WebSocket connection, authenticate it against the requested channel, and stream messages until disconnect
	 */
	public function handleClient(
		WebsocketClient $client,
		Request $request,
		Response $response,
	): void {
		$client_id = $client->getId();
		$this->logger->debug("Client connected", ['client_id' => $client_id]);

		$this->active_connections++;
		if ($this->active_connections >= $this->max_connections) {
			$this->logger->warning("WebSocket connections at capacity ({$this->active_connections}/{$this->max_connections}); new connections will start queuing", [
				'active_connections' => $this->active_connections,
				'max_connections' => $this->max_connections,
			]);
		}

		try {
			// Parse channel from query string
			$query = $request->getUri()->getQuery();
			parse_str($query, $params);

			// Check if channel is provided
			if (empty($params['channel'])) {
				$this->logger->warning("Client connected without channel parameter", [
					'client_id' => $client_id
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
			if ($channel == '') {
				$this->sendMessage($client, [
					'type' => 'error',
					'message' => 'Invalid channel name',
					'code' => 'INVALID_CHANNEL'
				]);
				$client->close();
				return;
			}

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
					'client_id' => $client_id,
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
				$this->logger->debug("Created gateway for dynamic channel: {$channel}");
			}

			// Add client to gateway
			$this->gateways[$channel]->addClient($client);
			$this->client_channels[$client_id] = $channel;

			$this->logger->debug("Client subscribed to channel", [
				'client_id' => $client_id,
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
			$this->logger->error("Error handling client {$client_id}: " . $e->getMessage());
		} finally {
			// Cleanup on disconnect
			$this->cleanupClient($client_id);
		}
	}

	/**
	 * Handle incoming message from client
	 */
	protected function handleClientMessage(WebsocketClient $client, string $payload): void
	{
		$client_id = $client->getId();
		$this->logger->debug("Received from client {$client_id}: {$payload}");

		try {
			$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

			if (isset($data['action'])) {
				$this->handleClientAction($client, $data);
			}
		} catch (\JsonException $e) {
			$this->logger->warning("Invalid JSON from client {$client_id}: " . $e->getMessage());
			$this->sendMessage($client, [
				'type' => 'error',
				'message' => 'Invalid JSON format'
			]);
		}
	}

	/**
	 * Handle client actions
	 */
	protected function handleClientAction(WebsocketClient $client, array $data): void
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
	protected function sendMessage(WebsocketClient $client, array $data): void
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
	protected function sanitizeChannelName(string $channel): string
	{
		$channel = urldecode($channel);
		// Allow only alphanumeric characters, hyphens, underscores, colons, and pipes
		if (!preg_match(MGR_Websocket_channel_regex::ALLOWED, $channel)) {
			return '';
		}

		return $channel;
	}

	/**
	 * Validate JWT channel in token
	 */
	protected function validateToken(string $token, string $channel): bool
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
	protected function cleanupClient(int $client_id): void
	{
		if (isset($this->client_channels[$client_id])) {
			$this->logger->debug("Client {$client_id} disconnected from channel: {$this->client_channels[$client_id]}");
			unset($this->client_channels[$client_id]);
		}

		$was_at_capacity = $this->active_connections >= $this->max_connections;
		$this->active_connections--;

		if ($was_at_capacity && $this->active_connections < $this->max_connections) {
			$this->logger->warning("WebSocket connections back below capacity ({$this->active_connections}/{$this->max_connections})", [
				'active_connections' => $this->active_connections,
				'max_connections' => $this->max_connections,
			]);
		}
	}
}
