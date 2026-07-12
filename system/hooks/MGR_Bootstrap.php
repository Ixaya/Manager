<?php

class MGR_Bootstrap
{
	//Example of different kind of imports if load timming doesnt matter
	public function init()
	{
		// CI3 is fully bootstrapped here — CI_Model, CI_Controller etc. exist
		// foreach (glob(__DIR__ . '/../third_party/core/MX/MX_*.php') as $file) {
		// 	require_once $file;
		// }
		// foreach (glob(__DIR__ . '/../core/MGR_*.php') as $file) {
		// 	require_once $file;
		// }
		foreach (glob(__DIR__ . '/../libraries/MGR_*.php') as $file) {
			require_once $file;
		}
		// foreach (glob(__DIR__ . '/../libraries/*/MGR_*.php') as $file) {
		// 	require_once $file;
		// }


		// $pkg = realpath(__DIR__ . '/../package');

		// $CI = &get_instance();
		// $CI->load->add_package_path($pkg);

		// Modules::$locations[$pkg . '/modules/'] = '../' . APPMGRPATH . 'package/modules/'; // Relative to APPPATH/controllers/
	}
}
