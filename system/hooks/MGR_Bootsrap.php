<?php

class MGR_Bootsrap
{
	public function init()
	{
		$CI = &get_instance();

		// CI3 is fully bootstrapped here — CI_Model, CI_Controller etc. exist
		foreach (glob(__DIR__ . '/../third_party/core/MX/MX_*.php') as $file) {
			require_once $file;
		}
		foreach (glob(__DIR__ . '/../system/core/MGR_*.php') as $file) {
			require_once $file;
		}
		foreach (glob(__DIR__ . '/../system/libraries/MGR_*.php') as $file) {
			require_once $file;
		}
		// foreach (glob(__DIR__ . '/../system/libraries/*/MGR_*.php') as $file) {
		// 	require_once $file;
		// }


		// $pkg = realpath(__DIR__ . '/../application');
		// $CI->load->add_package_path($pkg);
		// Modules::$locations[$pkg . '/modules/'] = '../../modules/';
	}
}
