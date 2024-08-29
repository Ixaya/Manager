<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Aws\S3\S3Client;
use Aws\CloudFront\CloudFrontClient;
use Aws\Credentials\Credentials;

class Amazons3
{

	//https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_basic-usage.html
	//https://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-s3.html
	//composer require aws/aws-sdk-php
	private $aws_bucket;
	private $aws_region;
	private $aws_cloud_front_id;
	private $aws_bucket_url;

	private $aws_accesskey;
	private $aws_secretkey;

	function __construct()
	{
		// parent::__construct();

		// Is the config file in the environment folder?
		if (
			!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/lib_amazons3.php')
			&& !file_exists($file_path = APPPATH . 'config/lib_amazons3.php')
		) {
			show_error('The configuration file lib_amazons3.php does not exist.');
		}

		include($file_path);

		$this->aws_bucket 		= $aws_bucket;
		$this->aws_region 		= $aws_region;
		$this->aws_cloud_front_id = $aws_cloud_front_id;
		$this->aws_bucket_url = $aws_bucket_url;

		$this->aws_accesskey 	= $aws_accesskey;
		$this->aws_secretkey	= $aws_secretkey;
	}

	//managerizar
	public function __get($var)
	{
		return get_instance()->$var;
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
		} catch (Aws\S3\Exception\S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
		}
	}

	public function upload_file($file_path, $s3_path = '', $invalidate_path = false)
	{
		try {
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

			$image_url = null;
			if (!empty($this->aws_bucket_url)) {
				$image_url = "{$this->aws_bucket_url}{$s3_path}";
			} else {
				$image_url = $result_metadata['effectiveUri'];
			}

			$v = dechex(time());
			$v = "?v=$v";

			return $image_url . $v;
		} catch (Aws\S3\Exception\S3Exception $e) {
			$error = $e->getMessage();

			log_message('ERROR', json_encode($error));
			return null;
		}
	}
	public function save_file($file_name, $path)
	{
		try {

			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key' => $file_name,
				'SaveAs' => "$path/$file_name"
			]);
			return $object;
		} catch (Aws\S3\Exception\S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
		}
	}

	public function get_file($file_name)
	{
		try {

			$s3 = $this->build_s3_client();

			$object = $s3->getObject([
				'Bucket' => $this->aws_bucket,
				'Key'    => $file_name
			]);

			return $object['Body'];
		} catch (Aws\S3\Exception\S3Exception $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));
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
		} catch (Aws\Exception\AwsException $e) {
			$error = $e->getMessage();
			log_message('ERROR', json_encode($error));

			return false;
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
