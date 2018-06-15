<?php

class Migration_Product extends CI_Migration {

    public function up() {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'name' => array(
                'type' => 'VARCHAR',
                'constraint' => 100
            )
            ,
            'category_id' => array(
                'type' => 'INT',
                'constraint' => 11
            )
            ,
            'brand_id' => array(
                'type' => 'INT',
                'constraint' => 11
            )
            ,
            'model' => array(
                'type' => 'VARCHAR',
                'constraint' => 150
            )
            ,
            'tag_line' => array(
                'type' => 'VARCHAR',
                'constraint' => 250
            )
            ,
            'material' => array(
                'type' => 'VARCHAR',
                'constraint' => 350
            )
            ,
            'features' => array(
                'type' => 'VARCHAR',
                'constraint' => 350
            )
            ,
            'colors' => array(
                'type' => 'VARCHAR',
                'constraint' => 350
            )
            ,
            'sizes' => array(
                'type' => 'VARCHAR',
                'constraint' => 350
            )
            ,
            'price' => array(
                'type' => 'INT',
                'constraint' => 11
            ),
            'image_url' => array(
                'type' => 'varchar',
                'constraint' => 500
			),
            'datasheet_url' => array(
                'type' => 'varchar',
                'constraint' => 500
            )
            ,
            'qty_at_hand' => array(
                'type' => 'INT',
                'constraint' => 11
            )
            ,
            'editorial_reviews' => array(
                'type' => 'VARCHAR',
                'constraint' => 750
            )
            ,
            'created_from_ip' => array(
                'type' => 'VARCHAR',
                'constraint' => 100
            ),
            'updated_from_ip' => array(
                'type' => 'VARCHAR',
                'constraint' => 100
            )
            ,
            'date_created' => array(
                'type' => 'TIMESTAMP'
            ),
            'last_update' => array(
                'type' => 'TIMESTAMP'
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('product');
    }

    public function down() {
        $this->dbforge->drop_table('product');
    }
}
