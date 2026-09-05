<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Opt-in web-controller base that guards dispatch against an uncaught
 * exception. A project only gets this by extending it (or
 * APP_Site_Controller) — never on MGR_Controller, never a global handler.
 */
class MGR_Site_Controller extends MGR_Controller
{
	/**
	 * Dispatches $method, routing an uncaught exception to
	 * MGR_Exceptions::show_exception() directly instead of CI3's own
	 * suppressed set_exception_handler() gate.
	 *
	 * @param array<int, mixed> $params
	 */
	public function _remap(string $method, array $params = []): void
	{
		// Defining _remap() makes CI3 skip its own missing/private/constructor
		// checks (CodeIgniter.php:409-440) and delegate entirely here.
		if (!$this->_dispatchable($method)) {
			show_404();

			return;
		}

		try {
			call_user_func_array([$this, $method], $params);
		} catch (\Throwable $ex) {
			$_error = &load_class('Exceptions', 'core');
			$_error->log_exception('error', 'Exception: ' . $ex->getMessage(), $ex->getFile(), $ex->getLine());
			$_error->show_exception($ex);
		}
	}

	/**
	 * Replicates the dispatch checks CI3 skips once _remap() is defined.
	 *
	 * @return bool False for an underscore-prefixed, CI_Controller-inherited,
	 *              missing, non-public, or constructor method.
	 */
	protected function _dispatchable(string $method): bool
	{
		if ($method === '' || $method[0] === '_'
			|| method_exists('CI_Controller', $method)
			|| !method_exists($this, $method)) {
			return false;
		}

		$reflection = new \ReflectionMethod($this, $method);

		return $reflection->isPublic() && !$reflection->isConstructor();
	}
}
