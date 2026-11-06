<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * A controller outside any module has a loader whose _module is null. The
 * booted suite loader is always inside a module, so each test builds a fresh
 * loader, which starts in that same state.
 */
class LoaderRootModuleTest extends CITestCase
{
	private function root_loader(): MY_Loader
	{
		$loader = new MY_Loader();

		$module = new ReflectionProperty($loader, '_module');
		$this->assertNull($module->getValue($loader), 'A fresh loader must be outside any module.');

		return $loader;
	}

	public function test_config_path_outside_a_module_matches_the_module_less_cascade(): void
	{
		$this->assertSame(
			$this->config->path('rest'),
			$this->root_loader()->config_path('rest'),
		);
	}

	public function test_config_read_outside_a_module_matches_the_module_less_cascade(): void
	{
		$this->assertSame(
			$this->config->read('rest'),
			$this->root_loader()->config_read('rest'),
		);
	}
}
