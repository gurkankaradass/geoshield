<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePropertiesTable extends Migration
{
    public function up()
    {
        // Mülkler tablosunun şemasını çıkarıyoruz
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => false,
            ],
            // Geometrik nokta verisi saklayacağımız alan (Enlem ve Boylamı MySQL POINT olarak tutacak)
            'location_geom' => [
                'type' => 'POINT',
                'null' => false,
            ],
            'risk_level' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'null' => false,
            ],
            'distance_km' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => false,
            ],
            'closest_fault_name' => [
                'type' => 'VARCHAR',
                'constraint' => '150',
                'null',
                false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('properties');

        // Mekansal sorguların (Spatial Search) uçması için location_geom alanına SPATIAL INDEX çakıyoruz
        $db = \Config\Database::connect();
        $db->query("ALTER TABLE properties ADD SPATIAL INDEX(location_geom)");
    }

    public function down()
    {
        $this->forge->dropTable('properties');
    }
}
