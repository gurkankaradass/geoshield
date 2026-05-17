<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GeoImport extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Geo';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'geo:import';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Imports fault lines from a KML file into the database.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'command:name [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $filePath = FCPATH . 'doc.kml'; // doc.kml dosyasını /public klasörüne koyduğunu varsayıyoruz

        if (!file_exists($filePath)) {
            CLI::error("Hata: doc.kml dosyası public/ klasöründe bulunamadı!");
            return;
        }

        CLI::write("KML okunuyor...", 'yellow');
        $xml = simplexml_load_file($filePath);

        // KML Namespace'lerini tanımlayalım (Genellikle 'kml' veya varsayılan olur)
        $namespaces = $xml->getDocNamespaces();
        $mainNamespace = current($namespaces);

        $xml->registerXPathNamespace('kml', current($namespaces));

        // Placemark içindeki LineString'leri bulalım
        $placemarks = $xml->xpath("//kml:Placemark");

        CLI::write(count($placemarks) . " adet veri bulundu. Aktarma başlıyor...", 'cyan');

        $count = 0;
        foreach ($placemarks as $placemark) {
            $name = (string)$placemark->name;

            $placemark->registerXPathNamespace('kml', $mainNamespace);
            // Coordinates kısmını alalım (LineString veya MultiGeometry olabilir, biz en yaygınını hedefliyoruz)
            $coords = $placemark->xpath(".//kml:coordinates");

            if (!empty($coords)) {
                $coordString = trim((string)$coords[0]);

                // KML formatındaki "lon,lat,alt" yapısını "lon lat" formatına çevirelim 
                $points = preg_split('/\s+/', $coordString);
                $formattedPoints = [];

                foreach ($points as $p) {
                    $parts = explode(',', $p);
                    if (count(($parts)) >= 2) {
                        // MySQL LINESTRING formatı: LON LAT (Virgül yok, boşluk var)
                        $formattedPoints[] = "{$parts[0]} {$parts[1]}";
                    }
                }

                if (count($formattedPoints) > 1) {
                    $lineStringWKT = "LINESTRING(" . implode(', ', $formattedPoints) . ")";

                    // Veritabanına Spatial olarak kaydedelim
                    $sql = "INSERT INTO fault_lines (name, line_geom) VALUES (?, ST_GeomFromText(?, 4326))";
                    $db->query($sql, [$name, $lineStringWKT]);
                    $count++;
                }
            }
        }

        CLI::write("İşlem tamamlandı! $count adet fay hattı aktarıldı.", 'green');
    }
}
