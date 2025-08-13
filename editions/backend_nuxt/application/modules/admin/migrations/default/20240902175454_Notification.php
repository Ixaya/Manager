<?php

class Migration_Notification extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field([
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => TRUE
            ),
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE
            ],
            'channel' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'event' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'data' => [
                'type' => 'TEXT',
            ],
            'read' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'deleted' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'last_update' => array(
                'type' => 'TIMESTAMP'
            ),
            'create_date' => array(
                'type' => 'TIMESTAMP'
            )
        ]);

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('user_id');
        $this->dbforge->create_table('notification');
    }

    public function down()
    {
        $this->dbforge->drop_table('notification');
    }
}
