<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserLocationsModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class UserLocationsController extends BaseController
{

    use ResponseTrait;
    /**
     * Tüm kayıtlı mülkleri listeler.
     */
    public function index()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");

        $model = new UserLocationsModel();
        // Model içindeki el yapımı uzamsal (spatial) metodumuzu çağırıyoruz
        $user_locations = $model->getAllUserLocations();

        return $this->respond($user_locations);
    }

    /**
     * Yeni bir mülkü mekansal POINT verisiyle birlikte kaydeder.
     */
    public function create()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            die();
        }

        $model = new UserLocationsModel();

        // Frontend'den gelen JSON verisini yakalıyoruz
        $json = $this->request->getJSON(true);

        if (!$json || empty($json['title']) || empty($json['lat']) || empty($json['lng'])) {
            return $this->fail('Eksik mülk bilgileri!', 400);
        }

        // MySQL 8 standardında [Lat Lng] (Enlem Boylam) sırasıyla POINT şablonunu kuruyoruz
        $lat = (float)$json['lat'];
        $lng = (float)$json['lng'];
        $pointWKT = "POINT($lat $lng)";

        $data = [
            'title' => esc($json['title']),
            'coord_geom' => $model->raw('ST_GeomFromText("' . $pointWKT . '", 4326)'), // Ham SQL geometrisi enjekte ediyoruz
            'risk_level' => esc($json['risk_level']),
            'distance_km' => (float)($json['distance_km']),
            'closest_fault_name' => esc($json['closest_fault_name'])
        ];

        if ($model->insert($data)) {
            return $this->respondCreated(['status' => 'success', 'message' => 'Mülk başarıyla güvenli bölgeye kaydedildi.']);
        }

        return $this->fail('Mülk kaydedilirken veritabanı hatası oluştu.', 500);
    }

    /**
     * Kimliği (ID) verilen mülkü veritabanından siler.
     */
    public function delete($id = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");

        if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
            die();
        }

        $model = new UserLocationsModel();

        if (!$id || !$model->find($id)) {
            return $this->failNotFound('Silinmek istenen mülk bulunamadı.');
        }

        if ($model->delete($id)) {
            return $this->respondDeleted(['status' => 'success', 'message' => 'Mülk başarıyla silindi.']);
        }

        return $this->fail('Mülk silinirken bir hata oluştu.', 500);
    }
}
