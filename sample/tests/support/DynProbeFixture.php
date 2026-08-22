<?php

defined('BASEPATH') or exit('No direct script access allowed');

// PHPUnit requires this file directly at suite-collection time, ahead of any
// CI_Loader::model() call that would normally pull MY_Model in first — the
// same guard CI_Loader::model() uses before requiring a model class file.
class_exists('CI_Model', false) or load_class('Model', 'core');

/**
 * Bare model with no methods beyond table wiring — shared by DynModelTest
 * (the "solely dyn mode" contract) and BaseModelTest (the MY_Model surface
 * dyn mode inherits), so both exercise the same model against the same
 * fixture table instead of each defining their own.
 */
class Dyn_probe_model extends APP_Model_Dyn
{
	public function __construct()
	{
		$this->table_name = 'dyn_probe_test';
		$this->soft_delete = true;
		$this->sync_timestamp_columns = ['create_date', 'last_update', 'import_date'];

		parent::__construct();
	}
}

/** Creates/drops the dyn_probe_test(+category) fixture tables shared by DynModelTest and BaseModelTest. */
final class DynProbeFixture
{
	private static ?object $migration = null;

	public static function create(): void
	{
		get_instance()->load->dbforge();
		require_once MGRPATH . 'libraries/MGR_Migration_builder.php';

		self::$migration = new class () extends MGR_Migration_builder {
			public function up(): void
			{
				$this->dbforge->add_field([
					...$this->field_id('id'),
					...$this->field(name: 'name', type: MgrFieldType::VarChar, constraint: 100),
					...$this->field(name: 'category_id', type: MgrFieldType::Int, unsigned: true, nullable: true),
					...$this->field(name: 'tenant_id', type: MgrFieldType::Int, unsigned: true, default: 1),
					...$this->field(name: 'status', type: MgrFieldType::SmallInt, default: 0),
					...$this->field(name: 'enabled', type: MgrFieldType::SmallInt, unsigned: true, default: 1),
					...$this->field(name: 'deleted', type: MgrFieldType::SmallInt, unsigned: true, default: 0),
					...$this->field(name: 'sync_enabled', type: MgrFieldType::SmallInt, unsigned: true, default: 0),
					...$this->field(name: 'import_date', type: MgrFieldType::Timestamp, nullable: true),
					...$this->field_timestamps(),
				]);
				$this->dbforge->add_key('id', true);
				$this->dbforge->create_table('dyn_probe_test');
				$this->modify_field_timestamp('dyn_probe_test');

				$this->dbforge->add_field([
					...$this->field_id('id'),
					...$this->field(name: 'name', type: MgrFieldType::VarChar, constraint: 100),
				]);
				$this->dbforge->add_key('id', true);
				$this->dbforge->create_table('dyn_probe_category_test');
			}

			public function down(): void
			{
				$this->dbforge->drop_table('dyn_probe_test');
				$this->dbforge->drop_table('dyn_probe_category_test');
			}
		};

		self::$migration->up();
	}

	public static function drop(): void
	{
		self::$migration?->down();
	}
}
