<?php

/**
 * Ion_auth_model result accessors on a fresh model (no query run yet) must
 * return type-consistent empties instead of dereferencing the null response.
 */
class ResultAccessorGuardsTest extends AuthTestCase
{
	private Ion_auth_model $fresh;

	protected function setUp(): void
	{
		// Not a DB-free test: the model constructor opens the DB connection,
		// so .env.testing must point at a reachable database.
		$this->fresh = new Ion_auth_model();
	}

	public function test_row_returns_null_before_any_query(): void
	{
		$this->assertNull($this->fresh->row());
	}

	public function test_row_array_returns_null_before_any_query(): void
	{
		$this->assertNull($this->fresh->row_array());
	}

	public function test_result_returns_empty_array_before_any_query(): void
	{
		$this->assertSame([], $this->fresh->result());
	}

	public function test_result_array_returns_empty_array_before_any_query(): void
	{
		$this->assertSame([], $this->fresh->result_array());
	}

	public function test_num_rows_returns_zero_before_any_query(): void
	{
		$this->assertSame(0, $this->fresh->num_rows());
	}
}
