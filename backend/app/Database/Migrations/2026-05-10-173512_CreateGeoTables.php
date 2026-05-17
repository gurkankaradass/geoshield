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
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'risk_score' => ['type' => 'FLOAT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('user_locations');

        // MariaDB/MySQL uyumlu Point ekleme
        $this->db->query("ALTER TABLE user_locations ADD coord_geom POINT NOT NULL");
        $this->db->query("ALTER TABLE user_locations ADD SPATIAL INDEX(coord_geom)");
    }

    public function down()
    {
        $this->forge->droptable('fault_lines');
        $this->forge->droptable('user_locations');
    }
}
