<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Aws\S3\S3Client;

 class Amazons3 {
	
	
	//https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_basic-usage.html
	//https://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-s3.html
	
	//managerizar
	public function __get($var)
	{
		return get_instance()->$var;
	}
	
	public $aws_bucket;
	public $aws_accesskey;
	public $aws_secretkey;
	
	
	public function list_files()
	{
		try {
			
			$s3 = new S3Client([
			    'version' => 'latest',
			    'region'  => 'us-west-2',
			    'credentials' => [
			        'key'    => $this->aws_accesskey,
			        'secret' => $this->aws_secretkey
			    ]
			]);

/*
		    $object = $s3->listObjectsV2([
		        'Bucket' => $this->aws_bucket
		    ]);
*/
		    
		    $iterator = $s3->getIterator('ListObjects', array(
			    'Bucket' => $this->aws_bucket
			));
			
			$objects = [];
			foreach ($iterator as $object) {
// 			    echo $object['Key'] . "\n";
			    $objects[] = $object['Key'];
			}
			
/*
			
			
		    
		    $json = json_encode($object);
		    $array = json_decode($json, TRUE);
*/
		    return $objects;
		    
		} catch (Aws\S3\Exception\S3Exception $e) {
		    $error = $e->getMessage();
		    log_message('ERROR',json_encode($error));
		    
		}
	}
	
	public function upload_file($file_path)
	{
		try {
			
			$s3 = new S3Client([
			    'version' => 'latest',
			    'region'  => 'us-west-2',
			    'credentials' => [
			        'key'    => $this->aws_accesskey,
			        'secret' => $this->aws_secretkey
			    ]
			]);

			$file_name = basename($file_path);
		    $s3->putObject([
		        'Bucket' => $this->aws_bucket,
		        'Key'    => $file_name,
		        'Body'   => fopen($file_path, 'r')
		    ]);
		} catch (Aws\S3\Exception\S3Exception $e) {
		    $error = $e->getMessage();
		    log_message('ERROR',json_encode($error));
		    
		}
	}
	public function save_file($file_name, $path)
	{
		
		try {
			
			$s3 = new S3Client([
			    'version' => 'latest',
			    'region'  => 'us-west-2',
			    'credentials' => [
			        'key'    => $this->aws_accesskey,
			        'secret' => $this->aws_secretkey
			    ]
			]);

		    $object = $s3->getObject([
		        'Bucket' => $this->aws_bucket,
		        'Key'    => $file_name,
		            'SaveAs' => "$path/$file_name"
		    ]);
		    return $object;
		    
		} catch (Aws\S3\Exception\S3Exception $e) {
		    $error = $e->getMessage();
		    log_message('ERROR',json_encode($error));
		    
		}
	}	
	public function get_file($file_name)
	{
		try {
			
			$s3 = new S3Client([
			    'version' => 'latest',
			    'region'  => 'us-west-2',
			    'credentials' => [
			        'key'    => $this->aws_accesskey,
			        'secret' => $this->aws_secretkey
			    ]
			]);

		    $object = $s3->getObject([
		        'Bucket' => $this->aws_bucket,
		        'Key'    => $file_name
		    ]);
		    return $object['Body'];
		    
		} catch (Aws\S3\Exception\S3Exception $e) {
		    $error = $e->getMessage();
		    log_message('ERROR',json_encode($error));
		    
		}
	}
	
}
