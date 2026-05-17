<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;

class MapController extends BaseController
{
    use ResponseTrait;

    /**
     * Ekranda görünen alanın (BBox) içerisindeki fay hatlarını döner.
     */
    public function getFaultLines()
    {
        $db = \Config\Database::connect();

        // Vue 3 ön yüzünden (Leaflet) gelecek olan harita sınır koordinatları
        $swLng = $this->request->getGet('swLng'); // Güneybatı Boylam
        $swLat = $this->request->getGet('swLat'); // Güneybatı Enlem
        $neLng = $this->request->getGet('neLng'); // Kuzeydoğu Boylam
        $neLat = $this->request->getGet('neLat'); // Kuzeydoğu Enlem

        // Eğer koordinatlar eksikse güvenlik amacıyla boş dizi dönelim
        if (!$swLng || !$swLat || !$neLng || !$neLat) {
            return $this->respond([]);
        }

        // Leaflet ekran sınırlarından (BBox) bir Poligon (Kutu) oluşturuyoruz
        // WKT Formatı: POLYGON((enlem boylam, enlem boylam, ...))
        $bboxWKT = "POLYGON(($swLng $swLat, $neLng $swLat, $neLng $neLat, $swLng $neLat, $swLng $swLat))";

        // MySQL Spatial Sorgusu: ST_Intersects ile kutunun içinden geçen çizgileri buluyoruz
        // ST_AsText(line_geom) ile de binary veriyi frontend'in anlayacağı metne anlık çeviriyoruz
        $sql = "SELECT id, name, type, ST_AsText(line_geom) as coordinates
        FROM fault_lines
        WHERE ST_Intersects(line_geom, ST_GeomFromText(?, 4326))";

        $query = $db->query($sql, [$bboxWKT]);
        $results = $query->getResultArray();

        // Frontend (Vue 3 + TS) için veriyi GeoJSON benzeri daha rahat okunur bir formata sokuyoruz
        $formattedData = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'coordinates' => $row['coordinates'] // Vue tarafında parse edeceğimiz LINESTRING(...) metni
            ];
        }, $results);

        return $this->respond($formattedData);
    }
}
