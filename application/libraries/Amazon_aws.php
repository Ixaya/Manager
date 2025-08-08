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
	private $aws_bucket;
	private $aws_region;
	private $aws_cloud_front_id;
	private $aws_bucket_url;
	private $aws_bedrock_model_id;

	private $aws_accesskey;
	private $aws_secretkey;

	private $config;
	private $config_key;

	function __construct()
	{
		// Is the config file in the environment folder?
		if (
			!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/lib_amazon_aws.php')
			&& !file_exists($file_path = APPPATH . 'config/lib_amazon_aws.php')
		) {
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

	public function set_config_key($key)
	{
		if (!empty($this->config[$key])) {
			$this->config_key = $key;
			$this->load_config($this->config[$key]);
		}
	}

	private function load_config($config)
	{
		$this->aws_bucket 		= $config['aws_bucket'] ?? '';
		$this->aws_region 		= $config['aws_region'] ?? '';
		$this->aws_cloud_front_id = $config['aws_cloud_front_id'] ?? '';
		$this->aws_bucket_url = $config['aws_bucket_url'] ?? '';
		$this->aws_bucket_url = $config['aws_bedrock_model_id'] ?? '';

		$this->aws_accesskey 	= $config['aws_accesskey'] ?? '';
		$this->aws_secretkey	= $config['aws_secretkey'] ?? '';
	}

	public function list_files()
	{
		try {
			$s3 = $this->build_s3_client();

			$iterator = $s3->getIterator('ListObjects', array(
				'Bucket' => $this->aws_bucket
			));

			$objects = [];
			foreach ($iterator as $object) {

				$objects[] = $object['Key'];
			}


			return $objects;
		} catch (S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
		}
	}

	public function upload_file($file_path, $s3_path = '', $invalidate_path = false)
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
	public function upload_data($data, $s3_path = '', $content_type = 'text/plain', $invalidate_path = false)
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
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
			return null;
		}
	}
	public function save_file($file_name, $path)
	{
		try {
			if (empty($file_name) || empty($path)) {
				return null;
			}

			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key' => $file_name,
				'SaveAs' => "$path/$file_name"
			]);
			return $object;
		} catch (S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
		}
	}

	public function get_file($file_path, &$file_mime = '')
	{
		try {
			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key'    => $file_path
			]);

			$file_mime = (!empty($object['ContentType'])) ? $object['ContentType'] : '';

			return $object['Body'];
		} catch (S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
		}
	}

	public function get_presigned_url($file_name, $bucket = '')
	{

		if (empty($file_name)) {
			log_message('ERROR', 'File name is required to generate a presigned URL.');
			return false;
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
			return false;
		} catch (Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', "Unexpected error while generating presigned URL: {$error}");
			return false;
		}
	}

	public function invalidate_path($path)
	{
		try {
			if (empty($path) || empty($this->aws_cloud_front_id)) {
				return false;
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
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));

			return false;
		}
	}

	public function textract_file($file_path, $queries = null)
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
			return $result;
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

	public function textract_file_lines($file_path)
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
	public function textract_document_config($file_path)
	{
		if (strpos($file_path, 's3/') !== false) {
			mngr_clean_file_s3_path($file_path);

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

	public function bedrock_converse($system_message, $user_message, $file_path = null, &$usage = null, $model_id = null)
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
				$file_extention = mngr_file_kind_extention($file_path, $mime_type, $kind);
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
				} else if ($kind == 'document') {
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
			$result = $this->bedrock_converse_raw($messages, $system_message, $model_id, $usage);

			return $result;
		} catch (Exception $e) {
			// Handle other exceptions, such as file read errors
			log_message('ERROR', json_encode($e->getMessage()));
			return ['error' => $e->getMessage()];
		}
	}

	public function bedrock_converse_raw($messages, $system_prompt, $model_id = null, &$usage = null)
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

			if (!empty($result['usage'])) {
				$usage = $result['usage'];
			}

			if (isset($result['output']['message']['content'][0]['text'])) {
				return $result['output']['message']['content'][0]['text'];
			} else {
				return ['error' => 'No response from AWS'];
			}

			return $result->getMessages()[0]->getContent();;
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

	public function bedrock_invoke_raw($payload, $model_id = null)
	{
		try {
			$client = $this->build_bedrock_client();

			if (empty($model_id)) {
				$model_id = $this->aws_bedrock_model_id;
			}

			$result = $client->invokeModel([
				'modelId' => $model_id,
				'contentType' => 'application/json',
				'body' => json_encode($payload),
			]);

			return $result['body'];
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


	private function build_s3_client()
	{
		return new S3Client([
			'version' => 'latest',
			'region'  => $this->aws_region,
			'credentials' => $this->build_credentials()
		]);
	}

	private function build_textract_client()
	{
		return new TextractClient([
			'version' => 'latest',
			'region'  => $this->aws_region,
			'credentials' => $this->build_credentials()
		]);
	}

	private function build_bedrock_client()
	{
		return new BedrockRuntimeClient([
			'version' => 'latest',
			'region'  => $this->aws_region,
			'credentials' => $this->build_credentials()
		]);
	}

	private function build_cf_client()
	{
		return new CloudFrontClient([
			'version'     => 'latest',
			'region'      => $this->aws_region,
			'credentials' => $this->build_credentials()
		]);
	}

	private function build_credentials()
	{
		return new Credentials($this->aws_accesskey, $this->aws_secretkey);
	}
}
