<?php

class Migration_Attachment extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'title', type: MgrFieldType::VarChar, constraint: 100),
			...$this->field(name: 'full_path', type: MgrFieldType::VarChar, constraint: 350),
			...$this->field(name: 'file_name', type: MgrFieldType::VarChar, constraint: 100),
			...$this->field(name: 'type', type: MgrFieldType::VarChar, constraint: 128),
			...$this->field(name: 'model_name', type: MgrFieldType::VarChar, constraint: 32),
			...$this->field(name: 'model_hash', type: MgrFieldType::VarChar, constraint: 32),
			...$this->field_timestamps()
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->add_key(['model_hash', 'model_name']);
		$this->dbforge->create_table('attachment');
	}

	public function down()
	{
		$this->dbforge->drop_table('attachment');
	}
}
