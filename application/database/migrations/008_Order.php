<?php

class Migration_Order extends CI_Migration {

    public function up() {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'email' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
            'phone' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
			'first_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
			'last_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
			'address1' => array(
                'type' => 'VARCHAR',
                'constraint' => 200
            ),
			'address2' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
			'city' => array(
                'type' => 'VARCHAR',
                'constraint' => 120
            ),
			'state' => array(
                'type' => 'VARCHAR',
                'constraint' => 32
            ),
			'zip' => array(
                'type' => 'VARCHAR',
                'constraint' => 5
            ),
			'subtotal' => array(
                'type' => 'DOUBLE',
            ),
			'shipping' => array(
                'type' => 'DOUBLE',
            ),
			'total' => array(
                'type' => 'DOUBLE',
            ),
			'auth_code' => array(
                'type' => 'VARCHAR',
                'constraint' => 50
            ),
            'req_code' => array(
                'type' => 'VARCHAR',
                'constraint' => 250
            ),
            'order_code' => array(
                'type' => 'VARCHAR',
                'constraint' => 80
            ),
            'last_update' => array(
                'type' => 'TIMESTAMP'
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('order');
    }

    public function down() {
        $this->dbforge->drop_table('order');
    }
}