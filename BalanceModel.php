<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\Json;

/**
 * Description of UserPinModel
 *
 * @author pilot
 */
class BalanceModel extends Model {
    public $address;
    private $api_url = 'https://ocean.defichain.com/v0/mainnet/address/';

    public function rules(): array {
        $rules = [
            [['address'], 'required']
        ];
        
        return array_merge($rules, parent::rules());
    }

    public function getCollectedData($address, $jsonDecode = TRUE) {
        return [
            'tokens' => $this->getWalletTokens($address, $jsonDecode),
            'balance' => $this->getWalletBalance($address, $jsonDecode),
        ];
    }
    
    public function getWalletTokens($jsonDecode = TRUE) {
        $sTokenUrl = $this->api_url . $this->address . '/tokens?size=200';
        
        $token_ch = curl_init($sTokenUrl);
        
        curl_setopt($token_ch, CURLOPT_URL, $sTokenUrl);
        curl_setopt($token_ch, CURLOPT_RETURNTRANSFER, true);

        //for debug only!
        curl_setopt($token_ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($token_ch, CURLOPT_SSL_VERIFYPEER, false);

        $tokens = curl_exec($token_ch);
        curl_close($token_ch);
        
        return $jsonDecode ? Json::decode($tokens) : $tokens;
    }
    //returns UTXO wallet address balance
    public function getWalletBalance($address = '', $jsonDecode = TRUE) {
        $sBalanceUrl = $this->api_url . $this->address . '/balance';
        $balance_ch = curl_init($sBalanceUrl);
        curl_setopt($balance_ch, CURLOPT_URL, $sBalanceUrl);
        curl_setopt($balance_ch, CURLOPT_RETURNTRANSFER, true);

        //for debug only!
        curl_setopt($balance_ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($balance_ch, CURLOPT_SSL_VERIFYPEER, false);

        $balance = curl_exec($balance_ch);

        return $jsonDecode ? Json::decode($balance) : $balance;
    }
    
    public function getWalletTransactions($jsonDecode = TRUE, $size = 30) {
        $sHistoryUrl = $this->api_url . $this->address . '/history';
        $history_ch = curl_init($sHistoryUrl);
        curl_setopt($history_ch, CURLOPT_URL, $sHistoryUrl);
        curl_setopt($history_ch, CURLOPT_RETURNTRANSFER, true);

        //for debug only!
        curl_setopt($history_ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($history_ch, CURLOPT_SSL_VERIFYPEER, false);

        $history = curl_exec($history_ch);

        return $jsonDecode ? Json::decode($history) : $history;
    }
}
