<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * The contract a scheduler or wrapper script keys on: a CLI failure goes to
 * stderr with exit 1, a result to stdout with exit 0. Each case runs
 * index.php in a child process — the error path ends in exit(), which would
 * end the test run if called in-process.
 */
class CliErrorContractTest extends CITestCase
{
	public function test_uncaught_exception_goes_to_stderr_with_exit_1(): void
	{
		$result = $this->run_cli('manager/tools/log_prune', 'bogus');

		$this->assertSame(1, $result['exit_code']);
		$this->assertSame('', $result['stdout']);
		$this->assertStringContainsString('**ERROR(500)**', $result['stderr']);
		$this->assertStringContainsString("unknown streams 'bogus'", $result['stderr']);
	}

	public function test_unknown_command_is_a_404_on_stderr_with_exit_1(): void
	{
		$result = $this->run_cli('manager/tools/no_such_command');

		$this->assertSame(1, $result['exit_code']);
		$this->assertSame('', $result['stdout']);
		$this->assertStringContainsString('**ERROR(404)**', $result['stderr']);
	}

	public function test_success_writes_stdout_only_with_exit_0(): void
	{
		$result = $this->run_cli('manager/tools/message', 'Contract');

		$this->assertSame(0, $result['exit_code']);
		$this->assertSame('Hello Contract!' . PHP_EOL, $result['stdout']);
		$this->assertSame('', $result['stderr']);
	}

	/**
	 * Runs a CLI route in a child process with display_errors off, the
	 * configuration where an uncaught exception used to print nothing.
	 *
	 * @return array{stdout: string, stderr: string, exit_code: int}
	 */
	private function run_cli(string ...$segments): array
	{
		$environment = array_merge(getenv(), [
			'CI_ENV'         => 'testing',
			'APP_ENV'        => 'testing',
			'REQUEST_METHOD' => 'GET',
		]);

		$process = proc_open(
			command: array_merge([PHP_BINARY, FCPATH . 'index.php'], $segments),
			descriptor_spec: [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			pipes: $pipes,
			cwd: FCPATH,
			env_vars: $environment,
		);
		$this->assertIsResource($process, 'Could not start the CLI child process.');

		fclose($pipes[0]);
		$stdout = (string) stream_get_contents($pipes[1]);
		$stderr = (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		return [
			'stdout'    => $stdout,
			'stderr'    => $stderr,
			'exit_code' => proc_close($process),
		];
	}
}
