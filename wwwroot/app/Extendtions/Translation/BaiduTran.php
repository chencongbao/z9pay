<?php

namespace App\Extendtions\Translation;

use Illuminate\Support\Facades\Http;

class BaiduTran
{
    protected static $language = [
        'zh_HK' => 'cht',   //繁体
        'en' => 'en',   //英文
        'fr' => 'fra',  //法语
        'pt' => 'pt',  //葡萄牙语
        'ar' => 'ara', //阿拉伯语
        'zh-CN' => 'zh', //简体中文
        'zh-TW' => 'cht', //繁体中文
        'de' => 'de', //德语
        'es' => 'spa',//西班牙语
        'ru' => 'ru', //俄语
        'pt-BR' => 'pot',//巴西葡萄牙语
        'pt-PT' => 'pt',//葡萄牙葡萄牙语
        'ja' => 'jp', //日语
        'ko' => 'kor',//韩语
        'it' => 'it',//意大利语
        'nl' => 'nl',//荷兰语
        'pl' => 'pl',//波兰语
        'sv' => 'swe',//瑞典语
        'no' => 'nor',//挪威语
        'da' => 'dan',//丹麦语
        'fi' => 'fin', //芬兰语
        'hu' => 'hu',//匈牙利语
        'cs' => 'cs', //捷克语
        'el' => 'el', //希腊语
        'ro' => 'rom',//罗马尼亚语
        'bg' => 'bul',//保加利亚语
        'tr' => 'tr',//土耳其语
        'vi' => 'vie',//越南语
        'th' => 'th',//泰语
        'id' => 'id',//印尼语
        'may' => "may"
    ];

    private $app_id = "20220928001356802";
    private $app_key = "u1YH9iLgiVNecAt9Hqxa";
    private $base_url = "https://fanyi-api.baidu.com/api/trans/vip/translate";
    private $from = "zh";
    private $to = "en";


    public function translate($string, $to = '')
    {
        if(!empty($to)){
            $this->to = $this->checkLanguage($to);
        }
        $response = Http::asForm()->post($this->base_url, $this->getQueryData($string));
        $result = json_decode($response->getBody(), true);
        return $this->response($result);
    }


    private function response($result)
    {
        $data = [];
        if (is_array($result) && isset($result['error_code'])) {
            throw new \Exception($result['error_code']);
        }
        if (is_array($result) && isset($result['trans_result'])) {
            foreach ($result['trans_result'] as $key => $value) {
                $data[] = $value['dst'];
            }
            return $data;
        }
        throw new \Exception("未返回翻译结果");
    }


    private function getQueryData($string)
    {
        $salt = time();
        $query = [
            "from" => $this->from,
            "to" => $this->to,
            "appid" => $this->app_id,
            "q" => $string,
            "salt" => $salt,
            "sign" => $this->getSign($string, $salt),
        ];
        return $query;
    }


    public function setFrom($from)
    {
        $this->from = $this->checkLanguage($from);
        return $this;
    }


    private function getSign($string, $time)
    {
        $str = $this->app_id . $string . $time . $this->app_key;
        return md5($str);
    }


    private function checkLanguage($language)
    {
        if (!isset(self::$language[$language])) {
            throw new \Exception("不支持".$language."语言");
        }
        return self::$language[$language];
    }
}
