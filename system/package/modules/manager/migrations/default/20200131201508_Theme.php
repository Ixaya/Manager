<?php

class Migration_Theme extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'kind', type: MgrFieldType::Int, constraint: 11),
			...$this->field(name: 'shortname', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'title', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'description', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'image_url', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'last_update', type: MgrFieldType::Timestamp),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('theme');
	}

	public function down()
	{
		$this->dbforge->drop_table('theme');
	}
}
