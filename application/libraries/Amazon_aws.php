<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\CloudFront\CloudFrontClient;
use Aws\Textract\TextractClient;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;

class Amazon_aws
{
	//https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_basic-usage.html
	//https://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-s3.html
	//composer require aws/aws-sdk-php
	private string $aws_bucket;
	private string $aws_region;
	private string $aws_cloud_front_id;
	private string $aws_bucket_url;
	private string $aws_bedrock_model_id;

	private string $aws_accesskey;
	private string $aws_secretkey;

	private array $config;
	private string $config_key;

	public function __construct()
	{
		$file_path = get_instance()->config->path('lib_amazon_aws');

		// Is the config file in the environment folder?
		if ($file_path === null) {
			show_error('The configuration file lib_amazon_aws.php does not exist.');
		}

		include($file_path);

		if (!$this->config_key && isset($active_config)) {
			$this->config_key = $active_config;
		}

		$this->config = isset($config) ? $config : [];

		if (!empty($config[$this->config_key])) {
			$this->load_config($config[$this->config_key]);
		}
	}

	public function set_config_key(string $key)
	{
		if (!empty($this->config[$key])) {
			$this->config_key = $key;
			$this->load_config($this->config[$key]);
		}
	}

	private function load_config(array $config)
	{
		$this->aws_bucket 		= $config['aws_bucket'] ?? '';
		$this->aws_region 		= $config['aws_region'] ?? '';
		$this->aws_cloud_front_id = $config['aws_cloud_front_id'] ?? '';
		$this->aws_bucket_url = $config['aws_bucket_url'] ?? '';
		$this->aws_bedrock_model_id = $config['aws_bedrock_model_id'] ?? '';

		$this->aws_accesskey 	= $config['aws_accesskey'] ?? '';
		$this->aws_secretkey	= $config['aws_secretkey'] ?? '';
	}

	public function list_files(): ?array
	{
		try {
			$s3 = $this->build_s3_client();

			$iterator = $s3->getIterator('ListObjects', [
				'Bucket' => $this->aws_bucket
			]);

			$objects = [];
			foreach ($iterator as $object) {

				$objects[] = $object['Key'];
			}


			return $objects;
		} catch (S3Exception $e) {
			log_message('ERROR', $e->getMessage());
			return null;
		}
	}

	public function upload_file(string $file_path, string $s3_path = '', bool $invalidate_path = false): ?string
	{
		try {
			if (empty($file_path) || !file_exists($file_path)) {
				return null;
			}

			$s3 = $this->build_s3_client();

			$file_type = mime_content_type($file_path);
			$result = $s3->putObject([
				'Bucket' => $this->aws_bucket,
				'Key'    => $s3_path,
				'Body'   => fopen($file_path, 'r'),
				'ContentType' => $file_type
			]);

			$result_metadata = $result->get('@metadata');

			$status = $result_metadata['statusCode'];

			if ($status != 200) {
				return null;
			}

			if ($invalidate_path) {
				$this->invalidate_path($s3_path);
				// log_message('ERROR', json_encode($cf_result['Status']));
			}

			$file_url = null;
			if (!empty($this->aws_bucket_url)) {
				$file_url = "{$this->aws_bucket_url}{$s3_path}";
			} else {
				$file_url = $result_metadata['effectiveUri'];
			}

			$v = dechex(time());
			$v = "?v=$v";

			return $file_url . $v;
		} catch (S3Exception $e) {
			$error = $e->getMessage();

			log_message('ERROR', json_encode($error));
			return null;
		}
	}
	public function upload_data(string $data, string $s3_path = '', string $content_type = 'text/plain', bool $invalidate_path = false): ?string
	{
		try {
			if (empty($data)) {
				return null;
			}

			$s3 = $this->build_s3_client();

			$result = $s3->putObject([
				'Bucket' => $this->aws_bucket,
				'Key' => $s3_path,
				'Body' => $data,
				'ContentType' => $content_type
			]);

			$result_metadata = $result->get('@metadata');
			$status = $result_metadata['statusCode'];

			if ($status != 200) {
				return null;
			}

			if ($invalidate_path) {
				$this->invalidate_path($s3_path);
			}

			$file_url = null;
			if (!empty($this->aws_bucket_url)) {
				$file_url = "{$this->aws_bucket_url}{$s3_path}";
			} else {
				$file_url = $result_metadata['effectiveUri'];
			}

			$v = dechex(time());
			$v = "?v=$v";
			return $file_url . $v;
		} catch (S3Exception $e) {
			log_message('ERROR', $e->getMessage());
			return null;
		}
	}
	public function save_file(string $file_name, string $path): bool
	{
		try {
			if (empty($file_name) || empty($path)) {
				return false;
			}

			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key' => $file_name,
				'SaveAs' => "$path/$file_name"
			]);
			return true;
		} catch (S3Exception $e) {
			log_message('ERROR', $e->getMessage());
			return false;
		}
	}

	public function get_file(string $file_path, &$file_mime = ''): ?string
	{
		try {
			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key'    => $file_path
			]);

			$file_mime = (!empty($object['ContentType'])) ? $object['ContentType'] : '';

			return $object['Body']->getContents();
		} catch (S3Exception $e) {
			log_message('ERROR', $e->getMessage());
			return null;
		}
	}

	public function get_presigned_url(string $file_name, string $bucket = ''): ?string
	{
		if (empty($file_name)) {
			log_message('ERROR', 'File name is required to generate a presigned URL.');
			return null;
		}

		if (empty($bucket)) {
			$bucket = $this->aws_bucket;
		}

		try {
			$s3 = $this->build_s3_client();

			$command = $s3->getCommand('GetObject', [
				'Bucket' => $bucket,
				'Key'    => $file_name,
			]);

			$presigned_url = $s3->createPresignedRequest($command, '+20 minutes')->getUri();
			return $presigned_url;
		} catch (S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', "Failed to generate presigned URL for file '{$file_name}' in bucket '{$bucket}': {$error}");
			return null;
		} catch (Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', "Unexpected error while generating presigned URL: {$error}");
			return null;
		}
	}

	public function invalidate_path(string $path): ?string
	{
		try {
			if (empty($path) || empty($this->aws_cloud_front_id)) {
				return null;
			}

			$valid_path = (substr($path, 0, 1) == '/') ? $path : "/$path"; //cf path needs to start with a '/'

			$cfClient = $this->build_cf_client();

			$result = $cfClient->createInvalidation([
				'DistributionId' => $this->aws_cloud_front_id,
				'InvalidationBatch' => [
					'Paths' => [
						'Quantity' => 1,
						'Items'    => [$valid_path]
					],
					'CallerReference' => uniqid()
				]
			]);

			return $result['Invalidation'];
		} catch (AwsException $e) {
			log_message('ERROR', $e->getMessage());
			return null;
		}
	}

	public function textract_file(string $file_path, ?array $queries = null): array
	{
		try {
			$client = $this->build_textract_client();

			$documentConfig = $this->textract_document_config($file_path);

			// Inicializar la configuración de las consultas
			$queriesConfig = [];
			if (!empty($queries)) {
				foreach ($queries as $query) {
					$queriesConfig[] = [
						'Text' => $query['Text'],
						'Pages' => $query['Pages'] ?? ['1'], // Páginas a consultar (por defecto, la primera)
					];
				}
			}

			// Definir los tipos de características
			$featureTypes = (!empty($queriesConfig)) ? ['FORMS', 'TABLES', 'QUERIES'] : ['FORMS', 'TABLES'];

			// Llamar a Textract con las características definidas
			$result = $client->analyzeDocument([
				'Document' => $documentConfig,
				'FeatureTypes' => $featureTypes,
				'QueriesConfig' => (!empty($queriesConfig)) ? ['Queries' => $queriesConfig] : [],
			]);

			// Procesar y devolver la respuesta
			return $result->toArray();
		} catch (AwsException $e) {
			// Manejo de errores
			log_message('ERROR', json_encode($e->getMessage()));
			return ['error' => $e->getMessage()];
		} catch (Exception $e) {
			// Handle other exceptions, such as file read errors
			log_message('ERROR', json_encode($e->getMessage()));
			return ['error' => $e->getMessage()];
		}
	}

	public function textract_file_lines(string $file_path)
	{
		try {
			$client = $this->build_textract_client();

			$documentConfig = $this->textract_document_config($file_path);

			// Llamar a Textract con las características definidas
			$result = $client->analyzeDocument([
				'Document' => $documentConfig,
				'FeatureTypes' => ['LAYOUT']
			]);

			// Procesar y devolver la respuesta
			$lines = [];
			if (!empty($result['Blocks'])) {
				foreach ($result['Blocks'] as $block) {
					if ($block['BlockType'] === 'LINE') {  // Focus only on lines of text
						$lines[] = $block['Text'];    // Concatenate lines with space
					}
				}
			}

			return $lines;
		} catch (AwsException $e) {
			// Manejo de errores
			log_message('ERROR', json_encode($e->getMessage()));
			return ['error' => $e->getMessage()];
		}
	}
	public function textract_document_config(string $file_path)
	{
		if (strpos($file_path, 's3/') !== false) {
			mgr_clean_file_s3_path($file_path);

			return [
				'S3Object' => [
					'Bucket' => $this->aws_bucket,
					'Name' => $file_path
				]
			];
		} else {
			$imageBytes = null;
			// Leer la imagen
			if (is_file($file_path)) {
				$imageBytes = file_get_contents($file_path);
			} else {
				throw new Exception("Error opening the image file. ({$file_path})");
			}

			return [
				'Bytes' => $imageBytes,
			];
		}
	}

	public function bedrock_converse(string $system_message, string|array $user_message, ?string $file_path = null, ?string $model_id = null)
	{
		try {
			if (empty($user_message)) {
				return ['error' => 'Prompt required'];
			}

			$message = [
				'content' => [],
				'role' => 'user',
			];

			if (is_array($user_message)) {
				foreach ($user_message as $_message) {
					$message['content'][] = ['text' => $_message];
				}
			} else {
				$message['content'][] = ['text' => $user_message];
			}

			if (!empty($file_path)) {
				$image_stream = file_get_contents($file_path);
				if ($image_stream === false) {
					throw new Exception("Error opening the image file.");
				}

				$kind = null;
				$mime_type = null;
				$file_extention = mgr_file_kind_extention($file_path, $mime_type, $kind);
				if ($file_extention == false) {
					throw new Exception("Unsupported file format: ");
				}

				if ($kind == 'image') {
					$message['content'][] = [
						'image' => [
							'format' => $file_extention,
							'source' => [
								'bytes' => $image_stream,
							]
						]
					];
				} elseif ($kind == 'document') {
					$message['content'][] = [
						'document' => [
							'format' => $file_extention,
							'name' => 'document',
							'source' => [
								'bytes' => $image_stream,
							]
						]
					];
				}
			}

			$messages = [$message];

			// Procesar y devolver la respuesta
			$result = $this->bedrock_converse_raw($messages, $system_message, $model_id);

			return $result;
		} catch (Exception $e) {
			// Handle other exceptions, such as file read errors
			log_message('ERROR', json_encode($e->getMessage()));
			return ['error' => $e->getMessage()];
		}
	}

	public function bedrock_converse_raw(array $messages, string $system_prompt, ?string $model_id = null): ?array
	{
		try {
			$client = $this->build_bedrock_client();

			if (empty($model_id)) {
				$model_id = $this->aws_bedrock_model_id;
			}

			$result = $client->converse([
				'messages' => $messages,
				'modelId' => $model_id,
				'system' => [['text' => $system_prompt]]
			]);

			$usage = null;
			if (!empty($result['usage'])) {
				$usage = $result['usage'];
			}

			$outputMessage = $result['output']['message'] ?? null;
			$contentBlocks = $outputMessage['content'] ?? [];

			$fullTextResponse = "";
			$toolCalls = [];

			foreach ($contentBlocks as $block) {
				if (isset($block['text'])) {
					$fullTextResponse .= $this->_cleanup_markdown($block['text']);
				}

				if (isset($block['toolUse'])) {
					$toolCalls[] = $block['toolUse'];
				}
			}

			return [
				'text' => $fullTextResponse,
				'tool_calls' => $toolCalls,
				'usage' => $usage,
				'error' => null
			];
		} catch (AwsException $e) {
			// Manejo de errores
			log_message('ERROR', json_encode($e->getMessage()));
			return ['text' => null, 'tool_calls' => null, 'error' => $e->getMessage()];
		} catch (Exception $e) {
			// Handle other exceptions, such as file read errors
			log_message('ERROR', json_encode($e->getMessage()));
			return ['text' => null, 'tool_calls' => null, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Invokes a Bedrock model and returns a standardized array.
	 * * @param array $payload
	 * @param string|null $model_id
	 * @return array|null
	 */
	public function bedrock_invoke_raw(array $payload, ?string $model_id = null): ?array
	{
		try {
			$client = $this->build_bedrock_client();
			$model_id = $model_id ?? $this->aws_bedrock_model_id;

			$result = $client->invokeModel([
				'modelId'     => $model_id,
				'contentType' => 'application/json',
				'body'        => json_encode($payload),
			]);

			return [
				'success' => true,
				'body'    => (string) $result['body'], // Casts stream to string safely
				'error'   => null
			];
		} catch (\Aws\Exception\AwsException $e) {
			log_message('ERROR', 'AWS Bedrock Error: ' . $e->getAwsErrorMessage());
			return [
				'success' => false,
				'body'    => null,
				'error'   => $e->getAwsErrorMessage()
			];
		} catch (\Exception $e) {
			log_message('ERROR', 'General PHP Error: ' . $e->getMessage());
			return [
				'success' => false,
				'body'    => null,
				'error'   => $e->getMessage()
			];
		}
	}

	public function bedrock_invoke_model(array $messages, string $system_prompt, ?string $model_id = null)
	{
		if (empty($model_id)) {
			$model_id = $this->aws_bedrock_model_id;
		}

		if (stripos($model_id, 'anthropic.claude') !== false || stripos($model_id, 'us.anthropic') !== false) {
			return $this->_invoke_claude($this->_normalize_messages_claude($messages), $system_prompt, $model_id);
		}

		if (stripos($model_id, 'amazon.nova') !== false || stripos($model_id, 'us.amazon.nova') !== false) {
			return $this->_invoke_nova($this->_normalize_messages_nova($messages), $system_prompt, $model_id);
		}

		if (stripos($model_id, 'mistral') !== false) {
			return $this->_invoke_mistral($this->_normalize_messages_mistral($messages), $system_prompt, $model_id);
		}

		return $this->_error("Unsupported model: {$model_id}");
	}

	// -------------------------------------------------------------------------

	private function _invoke_claude(array $messages, string $system_prompt, string $model_id)
	{
		$payload = [
			'anthropic_version' => 'bedrock-2023-05-31',
			'max_tokens'        => 1024,
			'temperature'       => 1.0,
			'top_p'             => 1.0,
			'system'            => $system_prompt,
			'messages'          => $messages,
		];

		$raw = $this->_invoke_raw($model_id, $payload);
		if ($raw['status'] === 'error') {
			return $raw;
		}

		return $this->_success(
			$raw['data']['content'][0]['text'] ?? null,
			$raw['data']['usage'] ?? null
		);
	}

	// -------------------------------------------------------------------------

	private function _invoke_nova(array $messages, string $system_prompt, string $model_id)
	{
		$payload = [
			'schemaVersion'   => 'messages-v1',
			'system'          => [['text' => $system_prompt]],
			'messages'        => $messages,
			'inferenceConfig' => [
				'max_new_tokens' => 1024,
				'temperature'    => 0,
				'topP'           => 1.0,
			],
		];

		$raw = $this->_invoke_raw($model_id, $payload);
		if ($raw['status'] === 'error') {
			return $raw;
		}

		return $this->_success(
			$raw['data']['output']['message']['content'][0]['text'] ?? null,
			$raw['data']['usage'] ?? null
		);
	}

	// -------------------------------------------------------------------------

	private function _invoke_mistral(array $messages, string $system_prompt, string $model_id)
	{
		$full_messages = array_merge(
			[['role' => 'system', 'content' => $system_prompt]],
			$messages
		);

		$payload = [
			'messages'    => $full_messages,
			'max_tokens'  => 1024,
			'temperature' => 0.7,
			'top_p'       => 1.0,
		];

		$raw = $this->_invoke_raw($model_id, $payload);
		if ($raw['status'] === 'error') {
			return $raw;
		}

		return $this->_success(
			$raw['data']['choices'][0]['message']['content'] ?? null,
			$raw['data']['usage'] ?? null
		);
	}

	// -------------------------------------------------------------------------

	private function _invoke_raw(string $model_id, array $payload)
	{
		try {
			$client = $this->build_bedrock_client();

			$result = $client->invokeModel([
				'modelId'     => $model_id,
				'contentType' => 'application/json',
				'accept'      => 'application/json',
				'body'        => json_encode($payload),
			]);

			$raw_body = $result['body']->getContents();

			return [
				'status' => 'ok',
				'data'   => json_decode($raw_body, true), // internal use only for extraction
			];
		} catch (AwsException $e) {
			log_message('ERROR', $e->getMessage());
			return $this->_error($e->getMessage());
		} catch (Exception $e) {
			log_message('ERROR', $e->getMessage());
			return $this->_error($e->getMessage());
		}
	}

	// -------------------------------------------------------------------------

	private function _success(string $body, $usage = null): array
	{
		return [
			'status' => 'ok',
			'body'   => $this->_cleanup_markdown($body),
			'usage'  => $usage,
		];
	}

	private function _error(string $message): array
	{
		return [
			'status' => 'error',
			'body'   => $message,
			'usage'  => null,
		];
	}

	private function _cleanup_markdown(string $text): string
	{
		// Strip markdown code fences: ```json ... ``` or ``` ... ```
		$text = preg_replace('/^```[a-z]*\s*/i', '', trim($text));
		$text = preg_replace('/\s*```$/', '', trim($text));

		return trim($text);
	}

	private function _normalize_messages_claude(array $messages)
	{
		$messages = array_map(function ($msg) {
			return ['type' => 'text', 'text' => $msg];
		}, $messages);

		return [['role' => 'user', 'content' => $messages]];
	}

	private function _normalize_messages_nova(array $messages)
	{
		$messages = array_map(function ($msg) {
			return ['text' => $msg];
		}, $messages);

		return [['role' => 'user', 'content' => $messages]];
	}

	private function _normalize_messages_mistral(array $messages)
	{
		return $messages = array_map(function ($msg) {
			return ['role' => 'user', 'content' => $msg];
		}, $messages);
	}


	private function build_s3_client()
	{
		return new S3Client($this->build_config());
	}

	private function build_textract_client()
	{
		return new TextractClient($this->build_config());
	}

	private function build_bedrock_client()
	{
		return new BedrockRuntimeClient($this->build_config());
	}

	private function build_cf_client()
	{
		return new CloudFrontClient($this->build_config());
	}
	private function build_config()
	{
		$config = [
			'version' => 'latest',
			'region'  => $this->aws_region,
		];

		if ($credentials = $this->build_credentials()) {
			$config['credentials'] = $credentials;
		}

		return $config;
	}
	private function build_credentials()
	{
		if (!empty($this->aws_accesskey) && !empty($this->aws_secretkey)) {
			return new Credentials($this->aws_accesskey, $this->aws_secretkey);
		}

		return null;
	}
}
