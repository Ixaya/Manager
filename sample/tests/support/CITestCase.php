<?php

use PHPUnit\Framework\TestCase;

/**
 * Base TestCase for tests that exercise framework code through the booted
 * CI instance (tests/Bootstrap.php boots the full framework once per
 * phpunit process; DB config comes from .env.testing / .env.testing.priv).
 */
abstract class CITestCase extends TestCase
{
	/**
	 * Expose CI super-object members ($this->db, $this->load, $this->config,
	 * loaded models…) exactly as controller/model code accesses them.
	 *
	 * @return mixed
	 */
	public function __get(string $name)
	{
		return get_instance()->$name;
	}
}
