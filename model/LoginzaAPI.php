<?php

namespace georgynet\loginzabb\model;

class LoginzaAPI
{
    /**
     * URL для получения данных о пользователе
     */
    const AUTH_INFO_URL = 'http://loginza.ru/api/authinfo';

    /**
     * Получить информацию профиля авторизованного пользователя.
     * @param string $token токен авторизованного пользователя
     * @return \stdClass
     */
    public function getAuthInfo($token)
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->get(self::AUTH_INFO_URL, [
            'query' => [
                'token' => $token
            ]
        ]);

        return $res->json([
            'object' => true
        ]);
    }
}
