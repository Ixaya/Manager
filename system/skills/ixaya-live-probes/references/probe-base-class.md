# Probe base class (paste once per repo)

Every probe controller needs the same utilities. Paste this once as
`application/modules/probes/controllers/api/Test_probe.php` (gitignored — it
can't ship as package code because it must extend the app-level
`APP_Rest_Controller`) and extend it:

```php
<?php

if (! defined('BASEPATH')) {
	exit('No direct script access allowed');
}

/**
 * Base for throwaway live probes. Real auth, no bypasses.
 */
abstract class Test_probe extends APP_Rest_Controller
{
	/** Reject the request unless real API-key auth ran. */
	protected function require_auth(): void
	{
		if (! isset($this->_apiuser)) {
			$this->response([
				'status'  => 0,
				'message' => 'No valid X-API-KEY supplied — probes deliberately run through real auth.',
			], REST_Controller::HTTP_UNAUTHORIZED);
		}
	}

	/** Read a protected/private property off an object. */
	protected function read_prop(object $obj, string $prop)
	{
		$rp = new ReflectionProperty($obj, $prop);
		$rp->setAccessible(true);
		return $rp->getValue($obj);
	}

	/** Invoke a protected/private method on an object. */
	protected function call_method(object $obj, string $method, array $args = [])
	{
		$rm = new ReflectionMethod($obj, $method);
		$rm->setAccessible(true);
		return $rm->invokeArgs($obj, $args);
	}

	/**
	 * Source of a method exactly as loaded at runtime (drift check that the
	 * running code is your edited tree, not a baked image).
	 *
	 * @return array{file: string, lines: string, src: string}
	 */
	protected function method_source(object $obj, string $method): array
	{
		$rm    = new ReflectionMethod($obj, $method);
		$lines = file($rm->getFileName());
		$src   = implode('', array_slice(
			$lines,
			$rm->getStartLine() - 1,
			$rm->getEndLine() - $rm->getStartLine() + 1
		));

		return [
			'file'  => $rm->getFileName(),
			'lines' => $rm->getStartLine() . '-' . $rm->getEndLine(),
			'src'   => $src,
		];
	}

	/**
	 * Capture EVERYTHING a callable emits — including what error_reporting
	 * masks (E_DEPRECATED is masked by default and never reaches the logs).
	 *
	 * @return array{result: mixed, captured: array<int, string>}
	 */
	protected function capture_errors(callable $fn): array
	{
		$captured = [];
		set_error_handler(function ($no, $str, $file = null, $line = null) use (&$captured) {
			$captured[] = $str . ($file ? " @ {$file}:{$line}" : '');
			return true;
		}, E_ALL);
		try {
			$result = $fn();
		} finally {
			restore_error_handler();
		}
		return ['result' => $result, 'captured' => $captured];
	}

	/** Register a throwaway user; returns id/identity/password. */
	protected function make_test_user(string $tag): array
	{
		$this->load->model('ion_auth_model', 'auth_model');
		$suffix   = mgr_generate_hash(8);
		$identity = $tag . '_' . $suffix;
		$id       = $this->auth_model->register($identity, 'Prb!' . $suffix . 'X9', $identity . '@example.test');

		return [
			'id'       => is_numeric($id) ? (int) $id : null,
			'identity' => $identity,
			'password' => 'Prb!' . $suffix . 'X9',
			'raw'      => $id,
		];
	}

	/** Register AND activate a user so it can log in. */
	protected function make_active_user(string $tag): array
	{
		$u = $this->make_test_user($tag);
		if ($u['id']) {
			$this->auth_model->activate($u['id']);
		}
		return $u;
	}
}
```

Probe shape on top of it: each `<item>_get()` calls `require_auth()`, runs a
private `check_<item>(): array` returning named booleans/values, and
responds with a `message` stating what "pass" looks like. Always clean up
created rows in `finally` (`delete_user`, unset planted session keys).

