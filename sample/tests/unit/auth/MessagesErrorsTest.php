<?php

/**
 * Message/error accumulators and their lang-line translation (messages()
 * loads the ion_auth lang file through the module-aware Lang class).
 */
class MessagesErrorsTest extends AuthTestCase
{
	protected function setUp(): void
	{
		self::$auth->clear_messages();
		self::$auth->clear_errors();
	}

	protected function tearDown(): void
	{
		self::$auth->clear_messages();
		self::$auth->clear_errors();
	}

	public function test_messages_empty_string_when_none_set(): void
	{
		$this->assertSame('', self::$auth->messages());
	}

	public function test_errors_empty_string_when_none_set(): void
	{
		$this->assertSame('', self::$auth->errors());
	}

	public function test_set_message_yields_a_translated_line(): void
	{
		self::$auth->set_message('login_successful');

		$msg = self::$auth->messages();
		$this->assertIsString($msg);
		$this->assertNotSame('', $msg);
	}

	public function test_set_error_yields_a_translated_line(): void
	{
		self::$auth->set_error('password_change_unsuccessful');

		$err = self::$auth->errors();
		$this->assertIsString($err);
		$this->assertNotSame('', $err);
	}

	public function test_array_accessors_return_arrays(): void
	{
		self::$auth->set_message('login_successful');
		self::$auth->set_error('password_change_unsuccessful');

		$this->assertIsArray(self::$auth->messages_array());
		$this->assertIsArray(self::$auth->errors_array());
	}

	public function test_clear_resets_both_accumulators(): void
	{
		self::$auth->set_message('login_successful');
		self::$auth->set_error('password_change_unsuccessful');

		self::$auth->clear_messages();
		self::$auth->clear_errors();

		$this->assertSame('', self::$auth->messages());
		$this->assertSame('', self::$auth->errors());
	}
}
