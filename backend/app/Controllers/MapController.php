<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Query;

class MapController extends BaseController
{
    use ResponseTrait;

    /**
     * Ekranda görünen alanın (BBox) içerisindeki fay hatlarını döner.
     */
    public function getFaultLines()
    {
        // Tarayıcı güvenlik duvarını (CORS) lokalde tamamen devre dışı bırakır
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }

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
        $bboxWKT = "POLYGON(($swLat $swLng, $swLat $neLng, $neLat $neLng, $neLat $swLng, $swLat $swLng))";

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

    /**
     * Tıklanan koordinatın en yakın fay hattına olan mesafesini ve risk durumunu hesaplar.
     */
    public function analyzeRisk()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
            die();
        }

        $db = \Config\Database::connect();

        // Frontend'den (Vue 3) gelecek olan tıklama koordinatları
        $lat = $this->request->getGet('lat'); // Enlem
        $lng = $this->request->getGet('lng'); // Boylam

        if (!$lat || !$lng) {
            return $this->fail('Koordinat bilgileri eksik!', 400);
        }

        $pointWKT = "POINT($lat $lng)";

        // Tüm MySQL ve MariaDB sürümlerinde %100 çalışan evrensel yöntem:
        // 1. ST_Distance ile düzlem üzerinde en yakın FAY hattını (çizgiyi) nokta atışı buluyoruz.
        // 2. Bulduğumuz bu en yakın fay hattını alıp, PHP tarafında mesafe analizi yapacağız.
        $sql = "SELECT id, name, type, 
                       ST_AsText(line_geom) AS line_text,
                       ST_Distance(line_geom, ST_GeomFromText(?, 4326)) AS distance_degrees
                FROM fault_lines 
                ORDER BY distance_degrees ASC 
                LIMIT 1";

        $query = $db->query($sql, [$pointWKT]);
        $closestFault = $query->getRowArray();

        if (!$closestFault) {
            return $this->respond([
                'status' => 'safe',
                'message' => 'Yakınlarda tanımlı bir fay hattı bulunamadı.'
            ]);
        }

        // --- HARİKA BİR MATEMATİK HİLESİ ---
        // Madem veritabanı çizgi-nokta arası küresel mesafeyi hesaplamıyor, 
        // biz de o en yakın çizginin (LINESTRING) koordinatlarını PHP array'ine çeviririz.
        // Sonra kullanıcının noktası ile o çizgideki tüm noktalar arasındaki gerçek küresel mesafeyi (Haversine)
        // SQL'in yerleşik ST_Distance_Sphere(POINT, POINT) fonksiyonuyla veritabanına tek tek hesaplatırız.

        $lineText = $closestFault['line_text']; // LINESTRING(35.1 38.2, 35.2 38.3, ...)
        // preg_match_with_matches: // Koordinatları temizleyelim
        preg_match_all('/([0-9.]+\s[0-9.]+)/', $lineText, $matches);

        $minDistanceMeter = PHP_FLOAT_MAX;

        foreach ($matches[0] as $coord) {
            // $coord = "35.1 38.2" -> "POINT(35.1 38.2)"
            $faultPointWKT = "POINT(" . $coord . ")";

            // İki NOKTA arasındaki mesafeyi MariaDB sorunsuz hesaplar!
            $distQuery = $db->query("SELECT ST_Distance_Sphere(ST_GeomFromText(?, 4326), ST_GeomFromText(?, 4326)) AS d", [$pointWKT, $faultPointWKT]);
            $distResult = $distQuery->getRowArray();

            if ($distResult && (float)$distResult['d'] < $minDistanceMeter) {
                $minDistanceMeter = (float)$distResult['d'];
            }
        }

        $distanceMeter = $minDistanceMeter;

        // Risk skalası algoritması
        if ($distanceMeter <= 500) {
            $riskLevel = 'Critical';
            $riskColor = '🔴';
        } elseif ($distanceMeter <= 2000) {
            $riskLevel = 'High';
            $riskColor = '🟠';
        } elseif ($distanceMeter <= 5000) {
            $riskLevel = 'Medium';
            $riskColor = '🟡';
        } else {
            $riskLevel = 'Low';
            $riskColor = '🟢';
        }

        return $this->respond([
            'input_coords' => [(float)$lat, (float)$lng],
            'fault_id'     => (int)$closestFault['id'],
            'fault_name'   => $closestFault['name'],
            'fault_type'   => $closestFault['type'],
            'distance_m'   => $distanceMeter,
            'distance_km'  => round($distanceMeter / 1000, 2),
            'risk_level'   => $riskLevel,
            'risk_color'   => $riskColor
        ]);
    }
}
