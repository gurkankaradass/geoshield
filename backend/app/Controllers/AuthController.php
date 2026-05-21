<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;

class AuthController extends BaseController
{
    use ResponseTrait;

    // JWT token üretimi için gizli bir anahtar (Gerçek projelerde bunu .env içinde saklamalıyız)
    private $jwtKey = "GeoShield_Super_Secret_Key_2026_Key";

    /**
     * Yeni Kullanıcı Kaydı (Register)
     */
    public function register()
    {

        $json = $this->request->getJSON(true);
        if (!$json || empty($json['username']) || empty($json['email']) || empty($json['password'])) {
            return $this->fail('Lütfen tüm alanları eksiksiz doldurun.', 400);
        }

        $model = new UserModel();

        // Email ve kullanıcı adı benzersizlik kontrolü
        if ($model->where('email', $json['email'])->first()) {
            return $this->fail('Bu e-posta adresi zaten kullanımda.', 400);
        }
        if ($model->where('username', $json['username'])->first()) {
            return $this->fail('Bu kullanıcı adı zaten alınmış.', 400);
        }

        $userData = [
            'username' => esc($json['username']),
            'email' => esc($json['email']),
            'password' => esc($json['password']) // Model içerisindeki callback bunu otomatik hash'leyecek
        ];

        if ($model->insert($userData)) {
            return $this->respondCreated(['status' => 'success', 'message' => 'Kaydınız başarıyla oluşturuldu. Giriş yapabilirsiniz.']);
        }

        return $this->fail('Kayıt oluşturulurken bir hata oluştu.', 500);
    }

    /**
     * Kullanıcı Girişi (Login) -> JWT Token Dönüyor
     */
    public function login()
    {
        $json = $this->request->getJSON(true);
        if (!$json || empty($json['email']) || empty($json['password'])) {
            return $this->fail('E-Posta ve şifre alanları zorunludur.', 400);
        }

        $model = new UserModel();
        $user = $model->where('email', $json['email'])->first();

        // Kullanıcı var mı ve şifre doğru mu kontrolü
        if (!$user || !password_verify($json['password'], $user['password'])) {
            return $this->fail('Hatalı e-posta veya şifre girdiniz.', 400);
        }

        // JWT Payload hazırlığı
        $iat = time(); // Token oluşturulma zamanı
        $exp = $iat + 3600; // 1 saat sonra expire (geçersiz) olacak

        $payload = [
            "iat" => $iat,
            "exp" => $exp,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "email" => $user['email']
            ]
        ];

        // HS256 algoritması ile şifrelenmiş token üretiyoruz
        $token = JWT::encode($payload, $this->jwtKey, 'HS256');

        return $this->respond([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ]);
    }
}
