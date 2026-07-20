# Writing tests

How to write and extend the PHPUnit suite. For how to *run* it — the `tools`
service, the testing environment, single-file invocation — see `docker.md`;
this document is only about authoring.

## The harness

`phpunit.xml` boots the whole framework once through `tests/Bootstrap.php`
before any test runs. The bootstrap pins the CLI environment, dispatches the
default CLI route (which only echoes) inside an output buffer so its output
never reaches the test report, and loads the support base classes. Every test
then reaches the running CodeIgniter instance through `$this->`, exactly as
controllers and models do.

Two base classes live under `tests/support/`; pick one:

- **`CITestCase`** — booted framework, no database. `$this->db`,
  `$this->config`, `$this->load`, and any loaded model are reachable via
  `$this->`. Use it for anything that does not need a schema.
- **`AuthTestCase`** — extends `CITestCase`, loads the Ion Auth model once per
  class, and adds namespaced fixture helpers. Use it for database-backed
  integration tests.

Both are `abstract`, so PHPUnit never collects them as tests. Do not extend
`PHPUnit\Framework\TestCase` directly — you lose the super-object access and
end up re-implementing what the bases already provide.

## Where tests live, and the two suites

``` text
tests/
├── Bootstrap.php        boots the framework once
├── support/             CITestCase, AuthTestCase (base classes)
└── unit/
    ├── example/         "Example Tests" suite — DB-free smoke check
    └── auth/            "Auth" suite — DB-backed integration reference
```

Each directory under `tests/unit/` is wired to a named `<testsuite>` in
`phpunit.xml`. To add a group, create the directory, put test classes in it,
and add a `<testsuite>` entry pointing at it.

- The **Example** suite is DB-free: it passes on a fresh checkout before any
  test database is wired, so it doubles as the "is the harness installed
  correctly?" check. Keep it minimal.
- The **Auth** suite is DB-backed: its tests hit a real database, which is why
  the scaffold ships it as the worked example of the integration pattern.

## A DB-free test

Extend `CITestCase`. The framework is already booted, so there is no setup —
assert against whatever the super-object exposes.

``` php
class ExampleTest extends CITestCase
{
	public function test_framework_boots(): void
	{
		$this->assertInstanceOf(CI_Controller::class, get_instance());
	}
}
```

## A DB-backed test

Extend `AuthTestCase` and use its fixture helpers. The rule that keeps a shared
dev database safe: **every fixture is namespaced and self-cleaning** — create
what you need with a recognizable prefix and remove it, so a crashed run never
collides with the next one.

``` php
class LoginExampleTest extends AuthTestCase
{
	public function test_login_succeeds(): void
	{
		self::create_active_user('phpunit_login');   // preclean + register + activate
		$this->assertTrue(self::$auth->login('phpunit_login', self::PASSWORD));
		self::delete_user_if_exists('phpunit_login');
	}
}
```

A read-only class may build one fixture set for the whole class in
`setUpBeforeClass()`; a class that mutates state should create and delete per
test. When you override `setUpBeforeClass()`, call `parent::setUpBeforeClass()`
first — the parent loads the model the helpers depend on.

## The assertion contract

`phpunit.xml` runs strict: `beStrictAboutTestsThatDoNotTestAnything`,
`failOnRisky`, and `failOnWarning` are all on. A test method with no assertion
fails the run, and so does any PHPUnit warning. Every test must assert
something.

## Gotchas

- The bootstrap pins `$_SERVER['argv']` so PHPUnit's own flags are not parsed
  as a CLI route. Do not read `$argv` from a test.
- Single-file runs need absolute paths, and `.env.testing` sets
  `APP_ENV=development` so PHP errors surface — both are covered in `docker.md`
  under running the suite.
