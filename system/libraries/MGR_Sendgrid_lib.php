<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Raw SendGrid Web API v3 (Mail Send) client — plain curl, no vendor SDK.
 */
class MGR_Sendgrid_lib
{
	protected const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

	protected array $config = [];
	protected string $config_key;

	protected ?string $api_key = null;
	protected bool $sandbox_mode = false;
	protected ?string $default_from_email = null;
	protected ?string $default_from_name = null;
	protected array $templates = [];

	public function __construct()
	{
		$file_path = get_instance()->config->path('lib_sendgrid');

		if ($file_path === null) {
			show_error('The configuration file lib_sendgrid.php does not exist.');
		}

		include($file_path);

		if (!isset($this->config_key) && isset($active_config)) {
			$this->config_key = $active_config;
		}
		$this->config = $config ?? [];

		if (!empty($this->config[$this->config_key])) {
			$this->load_config($this->config[$this->config_key]);
		}
	}

	public function set_config_key(string $key): void
	{
		if (!empty($this->config[$key])) {
			$this->config_key = $key;
			$this->load_config($this->config[$key]);
		}
	}

	protected function load_config(array $config): void
	{
		$this->api_key = $config['api_key'] ?? null;
		$this->sandbox_mode = $config['sandbox_mode'] ?? false;
		$this->default_from_email = $config['default_from_email'] ?? null;
		$this->default_from_name = $config['default_from_name'] ?? null;
		$this->templates = $config['templates'] ?? [];
	}

	/**
	 * Resolves a logical template name to this profile's SendGrid template id.
	 */
	public function get_template_id(string $key): ?string
	{
		return $this->templates[$key] ?? null;
	}

	/**
	 * Sends a plain HTML email through SendGrid's v3 Mail Send API.
	 *
	 * @param array<int, array{email: string, name?: string}> $to
	 * @param array{email: string, name?: string}|null $from falls back to this profile's default sender
	 * @param array{email: string, name?: string}|null $bcc
	 * @param array<int, array{path: string, filename?: string}> $attachments
	 * @return array{success: bool, status_code: ?int, headers: array<string, string>, body: ?string, message_id: ?string}
	 */
	public function send(array $to, string $subject, string $html_body, ?array $from = null, ?array $bcc = null, array $attachments = []): array
	{
		$payload = [
			'personalizations' => [$this->build_personalization(to: $to, subject: $subject, bcc: $bcc)],
			'from' => $this->build_from($from),
			'content' => [
				['type' => 'text/html', 'value' => $html_body],
			],
		];

		return $this->post($this->apply_common_settings($payload, $attachments));
	}

	/**
	 * Sends a SendGrid template email — dynamic (`d-` prefixed) or legacy, both supported.
	 *
	 * @param array<int, array{email: string, name?: string}> $to
	 * @param array<string, mixed> $template_data
	 * @param array{email: string, name?: string}|null $from falls back to this profile's default sender
	 * @param array{email: string, name?: string}|null $bcc
	 * @param array<int, array{path: string, filename?: string}> $attachments
	 * @return array{success: bool, status_code: ?int, headers: array<string, string>, body: ?string, message_id: ?string}
	 */
	public function send_template(array $to, string $template_id, array $template_data = [], ?array $from = null, ?array $bcc = null, array $attachments = []): array
	{
		$personalization = $this->build_personalization(to: $to, subject: null, bcc: $bcc);

		// (object) cast: json_encode([]) emits `[]`, but SendGrid requires a JSON
		// object here even when empty
		if (str_starts_with($template_id, 'd-')) {
			$personalization['dynamic_template_data'] = (object) $template_data;
		} else {
			$personalization['substitutions'] = (object) $template_data;
		}

		$payload = [
			'personalizations' => [$personalization],
			'from' => $this->build_from($from),
			'template_id' => $template_id,
		];

		return $this->post($this->apply_common_settings($payload, $attachments));
	}

	/**
	 * @param array<int, array{path: string, filename?: string}> $attachments
	 */
	protected function apply_common_settings(array $payload, array $attachments): array
	{
		if (!empty($attachments)) {
			$payload['attachments'] = $this->build_attachments($attachments);
		}

		if ($this->sandbox_mode) {
			$payload['mail_settings']['sandbox_mode']['enable'] = true;
		}

		return $payload;
	}

	/**
	 * @param array<int, array{email: string, name?: string}> $to
	 * @param array{email: string, name?: string}|null $bcc
	 * @return array{to: array<int, array{email: string, name?: string}>, subject?: string, bcc?: array<int, array{email: string, name?: string}>}
	 */
	protected function build_personalization(array $to, ?string $subject, ?array $bcc = null): array
	{
		$personalization = ['to' => $to];

		if ($subject !== null) {
			$personalization['subject'] = $subject;
		}

		if (!empty($bcc)) {
			$personalization['bcc'] = [$bcc];
		}

		return $personalization;
	}

	/**
	 * Splits a comma-separated address string into SendGrid's recipient shape.
	 *
	 * @return array<int, array{email: string}>
	 */
	public function build_recipients(string $email): array
	{
		return array_map(
			fn (string $address) => ['email' => $address],
			array_map('trim', explode(',', $email))
		);
	}

	/**
	 * Fills in this profile's default sender for whatever `$from` doesn't override.
	 *
	 * @param array{email: string, name?: string}|null $from
	 * @return array{email: string, name?: string}
	 */
	public function build_from(?array $from): array
	{
		$email = $from['email'] ?? $this->default_from_email ?? '';
		$name = $from['name'] ?? $this->default_from_name ?? null;

		$built = ['email' => $email];

		if (!empty($name)) {
			$built['name'] = $name;
		}

		return $built;
	}

	/**
	 * @param array<int, array{path: string, filename?: string}> $attachments
	 * @return array<int, array{content: string, filename: string, type: string, disposition: string}>
	 */
	protected function build_attachments(array $attachments): array
	{
		$built = [];

		foreach ($attachments as $attachment) {
			if (empty($attachment['path']) || !file_exists($attachment['path'])) {
				continue;
			}

			$built[] = [
				'content' => base64_encode(file_get_contents($attachment['path'])),
				'filename' => $attachment['filename'] ?? basename($attachment['path']),
				'type' => mime_content_type($attachment['path']) ?: 'application/octet-stream',
				'disposition' => 'attachment',
			];
		}

		return $built;
	}

	/**
	 * @return array{success: bool, status_code: ?int, headers: array<string, string>, body: ?string, message_id: ?string}
	 */
	protected function post(array $payload): array
	{
		if (empty($this->api_key)) {
			log_message('error', 'MGR_Sendgrid_lib: sendgrid not configured, missing api_key');
			return ['success' => false, 'status_code' => null, 'headers' => [], 'body' => null, 'message_id' => null];
		}

		$response_headers = [];

		$curl = curl_init(self::API_ENDPOINT);

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $this->api_key,
				'Content-Type: application/json',
			],
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_HEADERFUNCTION => function (\CurlHandle $ch, string $header) use (&$response_headers): int {
				$parts = explode(':', $header, 2);
				if (count($parts) === 2) {
					$response_headers[trim($parts[0])] = trim($parts[1]);
				}
				return strlen($header);
			},
		]);

		$body = curl_exec($curl);
		$status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($curl);
		curl_close($curl);

		if ($body === false) {
			log_message('error', "MGR_Sendgrid_lib: curl error: $curl_error");
			return ['success' => false, 'status_code' => null, 'headers' => [], 'body' => null, 'message_id' => null];
		}

		// 202 = accepted for real delivery; 200 = sandbox_mode validated it without sending
		$success = in_array($status_code, [200, 202], true);

		if (!$success) {
			log_message('error', "MGR_Sendgrid_lib: send failed, status $status_code: $body");
		}

		return [
			'success' => $success,
			'status_code' => $status_code,
			'headers' => $response_headers,
			'body' => $body ?: null,
			'message_id' => $this->find_header($response_headers, 'X-Message-Id'),
		];
	}

	/**
	 * Case-insensitive header lookup.
	 *
	 * @param array<string, string> $headers
	 */
	protected function find_header(array $headers, string $name): ?string
	{
		foreach ($headers as $key => $value) {
			if (strcasecmp($key, $name) === 0) {
				return $value;
			}
		}

		return null;
	}
}
