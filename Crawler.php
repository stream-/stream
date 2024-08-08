<?php

namespace app\models;

use yii\httpclient\Client;

class Crawler {
    const API_DOMAIN = 'https://ocean.defichain.com';
    const API_V = 'v0';
    const NETWORK = 'mainnet';

    public static function fetchTokens($page = NULL) {
        $address = self::compoundAddress('prices');
        return self::fetchData($address, $page);
    }

    public static function fetchPoolpairs() {
        $address = self::compoundAddress('poolpairs');
        return self::fetchData($address, NULL, 1000);
    }

    public static function fetchPrice($token) {
        $address = self::compoundAddress('prices/' . $token);
        return self::fetchData($address);
    }

    public static function fetchPrices($token = '', $page = NULL) {
        if ($token) {
            $address = self::compoundAddress('prices/' . $token . '/feed');
            return self::fetchData($address, $page);
        }

        return FALSE;
    }

    private static function fetchData($address, $page = NULL, $size = 200) {
        $address .= '?size=' . $size;

        if ($page) {
            $address .= '&next=' . $page;
        }
        $response = null;
        $client = new Client();
        try {
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($address)
//            ->setData(['' => ''])
            ->send();
        } catch (\Exception $e) {
            
            return null;
        }
        if (!empty($response)) {
            if ($response->isOk) {
                return $response->data;
            }
        }

        return NULL;
    }

    private static function compoundAddress($address) {
        return self::API_DOMAIN . '/' . self::API_V . '/' . self::NETWORK . '/' . $address;
    }
}