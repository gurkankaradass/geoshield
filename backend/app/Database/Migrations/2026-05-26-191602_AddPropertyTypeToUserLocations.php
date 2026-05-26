<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPropertyTypeToUserLocations extends Migration
{
    public function up()
    {
        $fields = [
            'property_type' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'default' => 'house', // Varsayılan olarak ev atayalım
                'after' => 'title' // title alanından hemen sonra gelsin
            ]
        ];

        $this->forge->addColumn('user_locations', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('user_locations', 'property_type');
    }
}
