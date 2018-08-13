<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Ion_auth extends CI_Migration {

	public function up()
	{
		// Drop table 'group' if it exists
		$this->dbforge->drop_table('group', TRUE);

		// Table structure for table 'group'
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE,
				'auto_increment' => TRUE
			),
			'name' => array(
				'type' => 'VARCHAR',
				'constraint' => '20',
			),
			'description' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
			)
		));
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('group');

		// Dumping data for table 'group'
		$data = array(
			array(
				'id' => '1',
				'name' => 'admin',
				'description' => 'Administrator'
			),
			array(
				'id' => '2',
				'name' => 'members',
				'description' => 'General User'
			)
		);
		$this->db->insert_batch('group', $data);


		// Drop table 'user' if it exists
		$this->dbforge->drop_table('user', TRUE);

		// Table structure for table 'user'
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE,
				'auto_increment' => TRUE
			),
			'ip_address' => array(
				'type' => 'VARCHAR',
				'constraint' => '16'
			),
			'username' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
			),
			'password' => array(
				'type' => 'VARCHAR',
				'constraint' => '80',
			),
			'salt' => array(
				'type' => 'VARCHAR',
				'constraint' => '40'
			),
			'email' => array(
				'type' => 'VARCHAR',
				'constraint' => '100'
			),
			'activation_code' => array(
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => TRUE
			),
			'forgotten_password_code' => array(
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => TRUE
			),
			'forgotten_password_time' => array(
				'type' => 'INT',
				'constraint' => '11',
				'unsigned' => TRUE,
				'null' => TRUE
			),
			'remember_code' => array(
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => TRUE
			),
			'created_on' => array(
				'type' => 'INT',
				'constraint' => '11',
				'unsigned' => TRUE,
			),
			'last_login' => array(
				'type' => 'INT',
				'constraint' => '11',
				'unsigned' => TRUE,
				'null' => TRUE
			),
			'active' => array(
				'type' => 'TINYINT',
				'constraint' => '1',
				'unsigned' => TRUE,
				'null' => TRUE
			),
			'first_name' => array(
				'type' => 'VARCHAR',
				'constraint' => '50',
				'null' => TRUE
			),
			'last_name' => array(
				'type' => 'VARCHAR',
				'constraint' => '50',
				'null' => TRUE
			),
			'company' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => TRUE
			),
			'phone' => array(
				'type' => 'VARCHAR',
				'constraint' => '20',
				'null' => TRUE
			),
			'image_name' => array(
				'type' => 'VARCHAR',
				'constraint' => '128',
				'null' => TRUE
			),
			'image_url' => array(
				'type' => 'VARCHAR',
				'constraint' => '254',
				'null' => TRUE
			),			
			'last_activity_date' => array(
				'type' => 'TIMESTAMP',
			),
			'last_activity_os' => array(
				'type' => 'TINYINT',
				'constraint' => '1',
				'unsigned' => TRUE
			),
			'client_id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '11',
				'unsigned' => TRUE
			),			
			'last_update' => array(
				'type' => 'TIMESTAMP',
			),
		));
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('user');

		// Dumping data for table 'users'
		$data = array(
			'id' => '1',
			'ip_address' => '127.0.0.1',
			'username' => 'admin@admin.com',
			'password' => '$2a$07$SeBknntpZror9uyftVopmu61qg0ms8Qv1yV6FG.kQOSM.9QhmTo36',
			'salt' => '',
			'email' => 'admin@admin.com',
			'activation_code' => '',
			'forgotten_password_code' => NULL,
			'created_on' => '1268889823',
			'last_login' => '1268889823',
			'active' => '1',
			'first_name' => 'Admin',
			'last_name' => 'istrator',
			'company' => 'ADMIN',
			'phone' => '0',
			'client_id' => '0',
		);
		$this->db->insert('user', $data);


		// Drop table 'user_group' if it exists
		$this->dbforge->drop_table('user_group', TRUE);

		// Table structure for table 'user_group'
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE,
				'auto_increment' => TRUE
			),
			'user_id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE
			),
			'group_id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE
			)
		));
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('user_group');

		// Dumping data for table 'user_group'
		$data = array(
			array(
				'id' => '1',
				'user_id' => '1',
				'group_id' => '1',
			),
			array(
				'id' => '2',
				'user_id' => '1',
				'group_id' => '2',
			)
		);
		$this->db->insert_batch('user_group', $data);


		// Drop table 'login_attempts' if it exists
		$this->dbforge->drop_table('login_attempt', TRUE);

		// Table structure for table 'login_attempt'
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => TRUE,
				'auto_increment' => TRUE
			),
			'ip_address' => array(
				'type' => 'VARCHAR',
				'constraint' => '16'
			),
			'login' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null', TRUE
			),
			'time' => array(
				'type' => 'INT',
				'constraint' => '11',
				'unsigned' => TRUE,
				'null' => TRUE
			)
		));
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('login_attempt');

	}

	public function down()
	{
		$this->dbforge->drop_table('user', TRUE);
		$this->dbforge->drop_table('group', TRUE);
		$this->dbforge->drop_table('user_group', TRUE);
		$this->dbforge->drop_table('login_attempt', TRUE);
	}
}
