<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Lang class */
require dirname(__FILE__) . "/../../third_party/MX/Lang.php";

// Same seam as MGR_Config: via MY_Lang, load_class() caches a module-aware
// Lang from the first call instead of relying on MX's mid-boot global swap,
// which never reaches the cache when the framework boots inside a function
// scope (PHPUnit bootstrap).
class MGR_Lang extends MX_Lang
{
	/**
	 * Merge every application/package/base layer that defines the language
	 * file, per key, instead of CI3's break-on-first-hit whole-file shadow.
	 * BASEPATH still loads first unconditionally, so the package's own
	 * translations of CI3's language files keep overriding it; only the
	 * package-path walk (application-first since add_package_path()) is
	 * reversed, so the application merges last.
	 */
	protected function load_fallback(string $langfile, string $idiom = '', bool $return = false, bool $add_suffix = true, string $alt_path = ''): array|bool|null
	{
		$langfile = str_replace('.php', '', $langfile);

		if ($add_suffix === true) {
			$langfile = preg_replace('/_lang$/', '', $langfile) . '_lang';
		}

		$langfile .= '.php';

		if (empty($idiom) || ! preg_match('/^[a-z_-]+$/i', $idiom)) {
			$config = &get_config();
			$idiom = empty($config['language']) ? 'english' : $config['language'];
		}

		if ($return === false && isset($this->is_loaded[$langfile]) && $this->is_loaded[$langfile] === $idiom) {
			return null;
		}

		// Load the base file, so any others found can override it
		$basepath = BASEPATH . 'language/' . $idiom . '/' . $langfile;
		$found = file_exists($basepath);

		if ($found === true) {
			include $basepath;
		}

		if ($alt_path !== '') {
			$alt_path .= 'language/' . $idiom . '/' . $langfile;
			if (file_exists($alt_path)) {
				include $alt_path;
				$found = true;
			}
		} else {
			foreach (array_reverse(get_instance()->load->get_package_paths(true)) as $package_path) {
				$package_path .= 'language/' . $idiom . '/' . $langfile;
				if ($basepath !== $package_path && file_exists($package_path)) {
					include $package_path;
					$found = true;
				}
			}
		}

		if ($found !== true) {
			show_error('Unable to load the requested language file: language/' . $idiom . '/' . $langfile);
		}

		// @phpstan-ignore booleanOr.alwaysTrue, isset.variable, variable.undefined ($lang is set by the dynamic include()s above, invisible to static analysis)
		if (! isset($lang) || ! is_array($lang)) {
			log_message('error', 'Language file contains no data: language/' . $idiom . '/' . $langfile);

			return $return === true ? [] : null;
		}

		if ($return === true) { // @phpstan-ignore deadCode.unreachable (reachable at runtime — see the ignore above)
			return $lang;
		}

		$this->is_loaded[$langfile] = $idiom;
		$this->language = array_merge($this->language, $lang);

		log_message('info', 'Language file loaded: language/' . $idiom . '/' . $langfile);

		return true;
	}
}
