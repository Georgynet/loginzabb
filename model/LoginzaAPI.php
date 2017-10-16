<?php

namespace georgynet\loginzabb\model;

class LoginzaAPI
{
    /**
     * URL для взаимодействия с API loginza
     */
    const API_URL = 'http://loginza.ru/api/%method%';

    /**
     * URL виджета Loginza
     */
    const WIDGET_URL = 'https://loginza.ru/api/widget';

    /**
     * Получить информацию профиля авторизованного пользователя.
     * @param string $token Токен ключ авторизованного пользователя
     * @return mixed
     */
    public function getAuthInfo($token)
    {
        return $this->apiRequest('authinfo', [
            'token' => $token]
        );
    }

    /**
     * Получает адрес ссылки виджета Loginza.
     * @param string $return_url Ссылка возврата, куда будет возвращен пользователя после авторизации
     * @param string $provider Провайдер по умолчанию из списка: google, yandex, mailru, vkontakte, facebook, twitter, loginza, myopenid, webmoney, rambler, mailruapi:, flickr, verisign, aol
     * @param string $overlay Тип встраивания виджета: true, wp_plugin, loginza
     * @return string
     */
    public function getWidgetUrl($return_url = null, $provider = null, $overlay = '')
    {
        $params = [];

        $params['token_url'] = $this->currentUrl();
        if ($return_url) {
            $params['token_url'] = $return_url;
        }

        if ($provider) {
            $params['provider'] = $provider;
        }

        if ($overlay) {
            $params['overlay'] = $overlay;
        }

        return self::WIDGET_URL . '?' . http_build_query($params);
    }

    /**
     * Возвращает ссылку на текущую страницу.
     * @return string
     */
    private function currentUrl()
    {
        $url = [];

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $url['sheme'] = "https";
            $url['port'] = '443';
        } else {
            $url['sheme'] = 'http';
            $url['port'] = '80';
        }

        $url['host'] = $_SERVER['HTTP_HOST'];

        if (strpos($url['host'], ':') === false && $_SERVER['SERVER_PORT'] != $url['port']) {
            $url['host'] .= ':' . $_SERVER['SERVER_PORT'];
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $url['request'] = $_SERVER['REQUEST_URI'];
        } else {
            $url['request'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
            $query = $_SERVER['QUERY_STRING'];
            if (isset($query)) {
                $url['request'] .= '?' . $query;
            }
        }

        return $url['sheme'] . '://' . $url['host'] . $url['request'];
    }

    /**
     * Делает запрос на API loginza.
     * @param string $method
     * @param array $params
     * @return string
     */
    private function apiRequest($method, $params)
    {
        $url = str_replace('%method%', $method, self::API_URL);

        $client = new \GuzzleHttp\Client();
        $res = $client->get($url, [
            'query' => $params
        ]);

        return $res->json([
            'object' => true
        ]);
    }
}
