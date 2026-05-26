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
     * Sadece giriş yapmış aktif kullanıcının mülklerini listeler.
     */
    public function index()
    {
        // Filtreden gelen user_id değerini alıyoruz
        $userId = $this->request->user_id;

        $model = new UserLocationsModel();

        // KRİTİK DEĞİŞİKLİK: Sadece bu kullanıcıya ait mülkleri getiriyoruz
        $user_locations = $model->where('user_id', $userId)->orderBy('created_at', 'DESC')->select('id, title, property_type, risk_level, distance_km, closest_fault_name, ST_X(coord_geom) as lat, ST_Y(coord_geom) as lng, created_at')->findAll();

        return $this->respond($user_locations);
    }

    /**
     * Yeni mülkü aktif kullanıcının id'siyle ilişkilendirerek kaydeder.
     */
    public function create()
    {
        $userId = $this->request->user_id; // Filtreden gelen user_id

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
            'user_id' => $userId, // Mülkü kullanıcıya zimmetliyoruz
            'title' => esc($json['title']),
            'property_type' => esc($json['property_type'] ?? 'location_dot'),
            'coord_geom' => new \CodeIgniter\Database\RawSql('ST_GeomFromText("' . $pointWKT . '", 4326)'), // Ham SQL geometrisi enjekte ediyoruz
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
     * Mülkü siler (Güvenlik için mülkün gerçekten o kullanıcıya ait olup olmadığı da kontrol edilebilir)
     */
    public function delete($id = null)
    {
        $userId = $this->request->user_id;
        $model = new UserLocationsModel();

        // Hem ID kontrolü hem de mülkün o kullanıcıya ait olup olmadığının kontrolü
        $location = $model->where('id', $id)->where('user_id', $userId)->first();

        if (!$location) {
            return $this->failNotFound('Silinmek istenen mülk bulunamadı veya bu işlem için yetkiniz yok.');
        }

        if ($model->delete($id)) {
            return $this->respondDeleted(['status' => 'success', 'message' => 'Mülk başarıyla silindi.']);
        }

        return $this->fail('Mülk silinirken bir hata oluştu.', 500);
    }
}
