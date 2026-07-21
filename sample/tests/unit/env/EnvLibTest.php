<?php

/**
 * Regression coverage for MGR_Env_lib's process-env resolution: the
 * empty-collapses-to-default contract, the strict=false opt-out, and
 * centralized quote-stripping. File-source precedence (.env/.env.priv) is
 * deliberately NOT covered here — it depends on real files under FCPATH.
 */
class EnvLibTest extends CITestCase
{
	private const FOOT_KEY = 'PHPUNIT_ENVLIB_FOOT';
	private const QUOTED_KEY = 'PHPUNIT_ENVLIB_QUOTED';
	private const QUOTED_EMPTY_KEY = 'PHPUNIT_ENVLIB_QUOTEDEMPTY';
	private const MISMATCH_KEY = 'PHPUNIT_ENVLIB_MISMATCH';
	private const ENV_SUPERGLOBAL_KEY = 'PHPUNIT_ENVLIB_SUPERGLOBAL';

	protected function tearDown(): void
	{
		foreach ([self::FOOT_KEY, self::QUOTED_KEY, self::QUOTED_EMPTY_KEY, self::MISMATCH_KEY] as $key) {
			putenv($key);
		}
		unset($_ENV[self::ENV_SUPERGLOBAL_KEY]);
	}

	public function test_empty_process_env_value_collapses_to_default(): void
	{
		putenv(self::FOOT_KEY . '=');

		$this->assertSame('DEF', mgr_env(self::FOOT_KEY, 'DEF'));
		$this->assertNull(mgr_env(self::FOOT_KEY));
	}

	public function test_strict_false_opts_out_to_verbatim_empty(): void
	{
		putenv(self::FOOT_KEY . '=');

		$this->assertSame('', mgr_env(self::FOOT_KEY, 'DEF', false));
	}

	public function test_typed_helpers_stay_safe_on_empty_value(): void
	{
		putenv(self::FOOT_KEY . '=');

		$this->assertTrue(mgr_env_bool(self::FOOT_KEY, true));
		$this->assertSame(7, mgr_env_int(self::FOOT_KEY, 7));
		$this->assertSame(['x'], mgr_env_array(self::FOOT_KEY, ['x']));
	}

	public function test_required_throws_on_empty_value(): void
	{
		putenv(self::FOOT_KEY . '=');

		$this->expectException(RuntimeException::class);
		mgr_env_required(self::FOOT_KEY);
	}

	public function test_resolve_source_reports_empty_process_env_as_not_set(): void
	{
		putenv(self::FOOT_KEY . '=');

		$row = Env_lib::resolve_source(self::FOOT_KEY);

		$this->assertSame('process-env', $row['source']);
		$this->assertFalse($row['set']);
		$this->assertSame(0, $row['length']);
	}

	public function test_matched_quote_pair_is_stripped(): void
	{
		putenv(self::QUOTED_KEY . '="secret"');

		$this->assertSame('secret', mgr_env(self::QUOTED_KEY));
	}

	public function test_quoted_empty_collapses_like_bare_empty(): void
	{
		putenv(self::QUOTED_EMPTY_KEY . '=""');

		$this->assertSame('DEF', mgr_env(self::QUOTED_EMPTY_KEY, 'DEF'));
	}

	public function test_mismatched_quote_survives_untouched(): void
	{
		putenv(self::MISMATCH_KEY . "='partial\"");

		$this->assertSame('\'partial"', mgr_env(self::MISMATCH_KEY));
	}

	public function test_env_superglobal_source_also_normalizes_empty(): void
	{
		$_ENV[self::ENV_SUPERGLOBAL_KEY] = '';

		$this->assertSame('DEF', mgr_env(self::ENV_SUPERGLOBAL_KEY, 'DEF'));
	}
}
