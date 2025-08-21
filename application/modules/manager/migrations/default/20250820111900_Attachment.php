<?php

class Migration_Attachment extends CI_Migration {

    public function up() {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'title' => array(
                'type' => 'VARCHAR',
                'constraint' => 100
            )
            ,
            'full_path' => array(
                'type' => 'VARCHAR',
                'constraint' => 350
            ),
            'file_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 100
            ),
			'type' => array(
                'type' => 'VARCHAR',
                'constraint' => 128
            ),
			'model_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 32
            ),
			'model_hash' => array(
                'type' => 'VARCHAR',
                'constraint' => 32
            ),
            'last_update' => array(
                'type' => 'TIMESTAMP'
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('attachment');
    }

    public function down() {
        $this->dbforge->drop_table('attachment');
    }
}