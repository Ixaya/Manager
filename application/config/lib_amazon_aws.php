<?php

defined('BASEPATH') or exit('No direct script access allowed');

$active_config = 'default';

$config['default']['aws_bucket']          = mgr_env('LIB_AWS_BUCKET', null);
$config['default']['aws_bucket_url']      = mgr_env('LIB_AWS_BUCKET_URL', null);

$config['default']['aws_region']          = mgr_env('LIB_AWS_REGION', 'us-east-1');
$config['default']['aws_cloud_front_id']  = mgr_env('LIB_AWS_CLOUD_FRONT_ID', null);

$config['default']['aws_bedrock_model_id'] = mgr_env('LIB_AWS_BEDROCK_MODEL_ID', null);

// Leave null if using EC2 AMI Role
$config['default']['aws_accesskey']       = mgr_env('LIB_AWS_ACCESSKEY', null);
$config['default']['aws_secretkey']       = mgr_env('LIB_AWS_SECRETKEY', null);
