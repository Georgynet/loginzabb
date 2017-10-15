<?php

namespace georgynet\loginzabb\model;

/**
 * Класса работы с Loginza API (http://loginza.ru/api-overview).
 *
 * @link http://loginza.ru/api-overview
 * @author Sergey Arsenichev, PRO-Technologies Ltd.
 * @version 1.0
 */
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
        return $this->apiRequert('authinfo', [
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
    private function apiRequert($method, $params)
    {
        $url = str_replace('%method%', $method, self::API_URL) . '?' . http_build_query($params);

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $user_agent = 'LoginzaAPI' . '/php' . phpversion();

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $raw_data = curl_exec($curl);
            curl_close($curl);
            $response = $raw_data;
        } else {
            $response = file_get_contents($url);
        }


        return json_decode($response);
    }

    public function debugPrint($responseData, $recursive = false)
    {
        if (!$recursive) {
            echo "<h3>Debug print:</h3>";
        }
        echo "<table border>";
        foreach ($responseData as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                echo "<tr><td>$key</td> <td><b>$value</b></td></tr>";
            } else {
                echo "<tr><td>$key</td> <td>";
                $this->debugPrint($value, true);
                echo "</td></tr>";
            }
        }
        echo "</table>";
    }
}
