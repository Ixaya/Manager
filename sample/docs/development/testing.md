# Writing tests

> Scope: writing and extending the PHPUnit suite. For how to *run* it — the
> `tools` service, the testing environment, single-file invocation — see
> `docker.md`; this document is only about authoring.

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

## Where tests live, and the two reference suites

``` text
tests/
├── Bootstrap.php        boots the framework once
├── support/             CITestCase, AuthTestCase (base classes)
└── unit/
    ├── example/         "Example Tests" suite — DB-free smoke check
    ├── auth/            "Auth" suite — DB-backed integration reference
    ├── env/             "Env" suite — the env layer
    ├── models/          "Models" suite — MY_Model / APP_Model_Dyn
    └── tools/           "Tools" suite — log_prune, the CLI error contract
```

Each directory under `tests/unit/` is wired to a named `<testsuite>` in
`phpunit.xml`. To add a group, create the directory, put test classes in it,
and add a `<testsuite>` entry pointing at it.

- The **Example** suite is DB-free: it passes on a fresh checkout before any
  test database is wired, so it doubles as the "is the harness installed
  correctly?" check. Keep it minimal.
- The **Auth** suite is DB-backed: its tests hit a real database, which is why
  the scaffold ships it as the worked example of the integration pattern.

The other suites cover the framework itself. They ship so a project that
overrides one of those classes can check its subclass still drives the
framework correctly.

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

## A path that ends in `exit()`

Some behavior only exists at process exit: an error render calls `exit(1)`,
a CLI command's contract is its stdout, stderr and exit code. Called
in-process, that `exit()` ends the PHPUnit run itself. Run the route in a
child process instead and assert the three outputs separately:

``` php
$process = proc_open(
	command: [PHP_BINARY, FCPATH . 'index.php', 'manager/tools/log_prune', 'bogus'],
	descriptor_spec: [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
	pipes: $pipes,
	cwd: FCPATH,
	env_vars: array_merge(getenv(), ['CI_ENV' => 'testing', 'APP_ENV' => 'testing']),
);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit_code = proc_close($process);
```

`tests/unit/tools/CliErrorContractTest.php` is the worked example, with the
call wrapped in a helper.

- **Pin the environment in `env_vars`.** Process env outranks the `.env`
  files, so `APP_ENV=testing` gives the child `display_errors` off — the
  production behavior — whatever `.env.testing` says for the test process
  itself.
- **Each case boots the whole framework.** Keep these few, and for the
  contract only. Logic that can be reached without exiting belongs in an
  ordinary in-process test.
- Reading stdout to the end before stderr is fine for short output. A child
  that writes more than a pipe buffer (about 64 KB) to stderr blocks before
  stdout closes; redirect such a child to temporary files instead of pipes.

## The assertion contract

`phpunit.xml` runs strict: `beStrictAboutTestsThatDoNotTestAnything`,
`failOnRisky`, and `failOnWarning` are all on. A test method with no assertion
fails the run, and so does any PHPUnit warning. Every test must assert
something.

**Assert values, not the driver's PHP types.** A column read back from the
database is a `string` under the native drivers and a real `int`/`float`
under PDO, so `assertSame(5, $row['status'])` passes on one driver and fails
on the other. Compare loosely, or cast both sides, or assert on a value you
control. Never make such a test pass by setting
`PDO::ATTR_STRINGIFY_FETCHES` on the connection — that flag is a deliberate
compatibility bridge for a project migrating an existing API contract, and
using it to satisfy an assertion silently changes what the whole suite is
testing.

## Gotchas

- The bootstrap pins `$_SERVER['argv']` so PHPUnit's own flags are not parsed
  as a CLI route. Do not read `$argv` from a test.
- Single-file runs need absolute paths, and `.env.testing` sets
  `APP_ENV=development` so PHP errors surface — both are covered in `docker.md`
  under running the suite.
