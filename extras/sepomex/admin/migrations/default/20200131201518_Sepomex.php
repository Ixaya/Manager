<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Sepomex extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'id_estado', type: MgrFieldType::Int),
			...$this->field(name: 'estado', type: MgrFieldType::VarChar, constraint: 35),
			...$this->field(name: 'id_municipio', type: MgrFieldType::Int),
			...$this->field(name: 'municipio', type: MgrFieldType::VarChar, constraint: 60),
			...$this->field(name: 'ciudad', type: MgrFieldType::VarChar, constraint: 60),
			...$this->field(name: 'zona', type: MgrFieldType::VarChar, constraint: 15),
			...$this->field(name: 'cp', type: MgrFieldType::VarChar, constraint: 5),
			...$this->field(name: 'asentamiento', type: MgrFieldType::VarChar, constraint: 70),
			...$this->field(name: 'tipo', type: MgrFieldType::VarChar, constraint: 30),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->add_key('cp');
		$this->dbforge->create_table('sepomex');
	}

	public function down()
	{
		$this->dbforge->drop_table('sepomex');
	}
}
