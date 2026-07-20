<?php

/**
 * Minimal example test — the phpunit harness smoke check.
 *
 * Extends CITestCase (not TestCase directly) so a consuming project copies the
 * intended paradigm: the framework boots once in tests/Bootstrap.php and the CI
 * super-object is reached through $this->. Asserts only DB-free facts, so it
 * passes on a fresh checkout before any test database is wired — the Auth suite
 * is the DB-backed integration reference.
 */
class ExampleTest extends CITestCase
{
	public function test_framework_boots(): void
	{
		$this->assertInstanceOf(CI_Controller::class, get_instance());
	}

	public function test_super_object_is_reachable(): void
	{
		// __get chains to the CI super-object, exactly as controller code reads it
		$this->assertNotNull($this->load);
	}
}
