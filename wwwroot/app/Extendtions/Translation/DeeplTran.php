<?php

namespace App\Extendtions\Translation;

use ChrisKonnertz\DeepLy\DeepLy;
use Illuminate\Support\Facades\Http;

class DeeplTran
{
    protected $api_key = "b32fe3dc-05bf-40bc-85cc-712883440d82:fx";

    protected static $language = [
        "BG"=>"BG",
        "CS"=>"CS",
        "DA"=>"DA",
        "DE"=>"DE",
        "EL"=>"EL",
        "EN-GB"=>"EN-GB",
        "EN-US"=>"EN-US",
        "en"=>"EN",
        "es"=>"ES",
        "ET"=>"ET",
        "FI"=>"FI",
        "FR"=>"FR",
        "HU"=>"HU",
        "IT"=>"IT",
        "ja"=>"JA",
        "LT"=>"LT",
        "LV"=>"LV",
        "NL"=>"NL",
        "PL"=>"PL",
        "PT-PT"=>"PT-PT",
        "pt-BR"=>"PT-BR",
        "PT"=>"PT",
        "RO"=>"RO",
        "RU"=>"RU",
        "SK"=>"SK",
        "SL"=>"SL",
        "SV"=>"SV",
        'zh-CN'=>"ZH",
        "id" => "ID",
        "tr" => "TR",
        "hi" => "HI"
    ];
    private $from = "ZH";
    private $to = "EN";

    private DeepLy $client;

    function __construct()
    {
        $this->client = new DeepLy($this->api_key);
    }

    public function translate($string, $to = '')
    {
        if(!empty($to)){
            $this->to = $this->checkLanguage($to);
        }
        try {
            $result = $this->client->translate($string,$this->to,$this->from);
            return explode("\n",$result);
        }catch (\Exception $e){
            return [];
        }

    }


    public function setFrom($from)
    {
        $this->from = $this->checkLanguage($from);
        return $this;
    }


    private function checkLanguage($language)
    {
        if (!isset(self::$language[$language])) {
            throw new \Exception("不支持".$language."语言");
        }
        return self::$language[$language];
    }
}
