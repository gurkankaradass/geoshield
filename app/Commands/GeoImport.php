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
        $filePath = FCPATH . 'doc.kml';

        if (!file_exists($filePath)) {
            CLI::error("Hata: doc.kml dosyası public/ klasöründe bulunamadı!");
            return;
        }

        CLI::write("KML okunuyor...", 'yellow');
        $xml = simplexml_load_file($filePath);

        $namespaces = $xml->getDocNamespaces();
        $mainNamespace = current($namespaces);
        $xml->registerXPathNamespace('kml', $mainNamespace);

        $placemarks = $xml->xpath("//kml:Placemark");
        CLI::write(count($placemarks) . " adet veri bulundu. Aktarma başlıyor...", 'cyan');

        // Önceki hatalı yüklemeleri temizlemek için tabloyu sıfırlayalım
        $db->query("TRUNCATE TABLE fault_lines");

        $count = 0;
        foreach ($placemarks as $placemark) {
            $placemark->registerXPathNamespace('kml', $mainNamespace);

            // 1. Description içerisindeki CDATA HTML metnini alıyoruz
            $description = (string)$placemark->description;

            $fayAdi = 'Bilinmeyen Fay Hattı';
            $fayTipi = 'Tanımlanmamış';

            if (!empty($description)) {
                // Regex ile <B>FAYADI</B> = DEĞER yapısını yakalıyoruz
                if (preg_match('/<B>FAYADI<\/B>\s*=\s*([^<]+)/u', $description, $matches)) {
                    $fayAdi = trim($matches[1]);
                }

                // Regex ile <B>FAYTIPI</B> = DEĞER yapısını yakalıyoruz
                if (preg_match('/<B>FAYTIPI<\/B>\s*=\s*([^<]+)/u', $description, $matches)) {
                    $fayTipi = trim($matches[1]);

                    // Eğer fay tipi sadece sayısal kod ise (Örn: 1), daha okunabilir yapabiliriz
                    if ($fayTipi === '1') {
                        $fayTipi = 'Diri Fay';
                    } elseif ($fayTipi === '2') {
                        $fayTipi = 'Olası Kuvaterner Fayı';
                    }
                }
            }

            // 2. Geometri İşlemleri (LINESTRING)
            $coords = $placemark->xpath(".//kml:coordinates");

            if (!empty($coords)) {
                $coordString = trim((string)$coords[0]);
                $points = preg_split('/\s+/', $coordString);
                $formattedPoints = [];

                foreach ($points as $p) {
                    $parts = explode(',', $p);
                    if (count($parts) >= 2) {
                        $formattedPoints[] = "{$parts[0]} {$parts[1]}";
                    }
                }

                if (count($formattedPoints) > 1) {
                    $lineStringWKT = "LINESTRING(" . implode(', ', $formattedPoints) . ")";

                    $sql = "INSERT INTO fault_lines (name, type, line_geom) VALUES (?, ?, ST_GeomFromText(?, 4326))";
                    $db->query($sql, [$fayAdi, $fayTipi, $lineStringWKT]);
                    $count++;
                }
            }
        }

        CLI::write("İşlem tamamlandı! $count adet fay hattı başarıyla aktarıldı.", 'green');
    }
}
