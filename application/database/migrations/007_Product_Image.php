<?php

class Migration_Product_Image extends CI_Migration {

    public function up() {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'product_id' => array(
                'type' => 'INT'
            ),
            'name' => array(
                'type' => 'VARCHAR',
                'constraint' => 325
            ),
            'url' => array(
                'type' => 'VARCHAR',
                'constraint' => 325
            ),
            'last_update' => array(
                'type' => 'TIMESTAMP'
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('product_image');
    }

    public function down() {
        $this->dbforge->drop_table('product_image');
    }

}