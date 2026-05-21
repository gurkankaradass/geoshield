<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeoTables extends Migration
{
    public function up()
    {
        // 1. Fay Hatları Tablosu
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('fault_lines');

        // MariaDB/MySQL uyumlu LineString ekleme (SRID kısmını şimdilik opsiyonel bırakalım veya CAST ile halledelim)
        $this->db->query("ALTER TABLE fault_lines ADD line_geom LINESTRING NOT NULL");
        $this->db->query("ALTER TABLE fault_lines ADD SPATIAL INDEX(line_geom)");

        // 2. Kullanıcı Konumları Tablosu
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // Şimdilik auth yoksa null kalabilir
            'title'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'coord_geom'      => ['type' => 'POINT', 'null' => false],
            'risk_level'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // Critical, High, Medium, Low
            'distance_km'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'closest_fault_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);

        // 🔥 YENİ: user_id alanını users tablosunun id alanına bağlıyoruz
        // users tablosundan bir kullanıcı silinirse mülkleri de otomatik silinsin (CASCADE)
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('user_locations');

        // Mekansal index alanımızı coord_geom olarak koruyoruz
        $db = \Config\Database::connect();
        $this->db->query("ALTER TABLE user_locations ADD SPATIAL INDEX(coord_geom)");
    }

    public function down()
    {
        $this->forge->droptable('fault_lines');
        $this->forge->droptable('user_locations');
    }
}
