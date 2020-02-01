<?php

class Migration_Sepomex extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'idEstado' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'estado' => array(
				'type' => 'VARCHAR',
				'constraint' => 35
			),
			'idMunicipio' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'municipio' => array(
				'type' => 'VARCHAR',
				'constraint' => 60
			),
			'ciudad' => array(
				'type' => 'VARCHAR',
				'constraint' => 60
			),
			'zona' => array(
				'type' => 'VARCHAR',
				'constraint' => 15
			),
			'cp' => array(
				'type' => 'MEDIUMINT',
				'constraint' => 11
			),
			'asentamiento' => array(
				'type' => 'VARCHAR',
				'constraint' => 70
			),
			'tipo' => array(
				'type' => 'VARCHAR',
				'constraint' => 20
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('sepomex');
	}

	public function down() {
		$this->dbforge->drop_table('sepomex');
	}

}

/*
CREATE TABLE IF NOT EXISTS `sepomex` (
`id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT ,
`idEstado` SMALLINT UNSIGNED NOT NULL ,
`estado` VARCHAR(35) NOT NULL ,
`idMunicipio` SMALLINT UNSIGNED NOT NULL ,
`municipio` VARCHAR(60) NOT NULL ,
`ciudad` VARCHAR(60),
`zona` VARCHAR(15) NOT NULL,
`cp` MEDIUMINT NOT NULL ,
`asentamiento` VARCHAR(70) NOT NULL ,
`tipo` VARCHAR(20) NOT NULL ,
PRIMARY KEY (`id`)
) ENGINE = InnoDB;
*/
