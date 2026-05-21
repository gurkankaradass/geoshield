<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthFilter implements FilterInterface
{
    // AuthController'da kullandığımız gizli anahtarın birebir aynısı
    private $jwtKey = "GeoShield_Super_Secret_Key_2026_Key";

    public function before(RequestInterface $request, $arguments = null)
    {
        // CORS Preflight (OPTIONS) isteklerini filtreye takılmadan doğrudan geçiriyoruz
        if ($request->getMethod() === 'options') {
            return;
        }

        // HTTP Başlıklarından Authorization bilgisini çekiyoruz
        $authHeader = $request->getServer('HTTP_AUTHORIZATION');

        if (!$authHeader) {
            // Alternatif başlık kontrolü (Bazı sunucu yapılandırmaları için)
            $authHeader = $request->header('Authorization');
        }

        if (!$authHeader) {
            $response = service('response');
            return $response->setJSON([
                'status' => 'error',
                'message' => 'Bu işlem için yetkiniz yok. Lütfen giriş yapın.'
            ])->setStatusCode(401);
        }

        try {
            // "Bearer <token>" formatındaki "Bearer " kısmını ayıklıyoruz
            $token = str_replace('Bearer ', '', (string)$authHeader);

            // Token'ı gizli anahtarımız ve HS256 algoritmasıyla çözüyoruz
            $decoded = JWT::decode($token, new Key($this->jwtKey, 'HS256'));

            // Çözülen kullanıcı kimlik bilgisini (user_id) controller katmanında 
            // yakalayabilmek için request nesnesine global bir değişken olarak enjekte ediyoruz
            $request->user_id = $decoded->user->id;
        } catch (Exception $e) {
            $response = service('response');
            return $response->setJSON([
                'status' => 'error',
                'message' => 'Oturum süreniz dolmuş veya geçersiz token. Lütfen tekrar giriş yapın.'
            ])->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Bu adımda bir işlem yapmamıza gerek yok
    }
}
