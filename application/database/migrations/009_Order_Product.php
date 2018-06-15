<?php

class Migration_Order_Product extends CI_Migration {

    public function up() {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'order_id' => array(
                'type' => 'INT',
                'constraint' => 11
            ),
            'product_id' => array(
                'type' => 'INT',
                'constraint' => 11
            ),
            'qty' => array(
                'type' => 'INT',
                'constraint' => 11
            ),
            'price' => array(
                'type' => 'DOUBLE'
            ),
            'subtotal' => array(
                'type' => 'DOUBLE'
            ),
            'last_update' => array(
                'type' => 'TIMESTAMP'
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('order_product');
    }

    public function down() {
        $this->dbforge->drop_table('order_product');
    }

}