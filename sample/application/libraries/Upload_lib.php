<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once MGRPATH . 'libraries/MGR_Upload_lib.php';

/** Project-wide upload_lib: images are re-encoded to WebP. */
class Upload_lib extends MGR_Upload_lib
{
	protected string $convert_to = 'webp';
}
