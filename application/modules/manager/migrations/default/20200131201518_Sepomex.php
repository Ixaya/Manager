<?php

class Migration_Sepomex extends MGR_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'idEstado', type: MgrFieldType::Int),
			...$this->field(name: 'estado', type: MgrFieldType::VarChar, length: 35),
			...$this->field(name: 'idMunicipio', type: MgrFieldType::Int),
			...$this->field(name: 'municipio', type: MgrFieldType::VarChar, length: 60),
			...$this->field(name: 'ciudad', type: MgrFieldType::VarChar, length: 60),
			...$this->field(name: 'zona', type: MgrFieldType::VarChar, length: 15),
			...$this->field(name: 'cp', type: MgrFieldType::VarChar, length: 5),
			...$this->field(name: 'asentamiento', type: MgrFieldType::VarChar, length: 15),
			...$this->field(name: 'tipo', type: MgrFieldType::VarChar, length: 15),
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
