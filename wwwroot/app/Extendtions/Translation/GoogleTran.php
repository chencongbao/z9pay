<?php

namespace App\Extendtions\Translation;


//AIzaSyBm388FnlK_Li_KAYVP-olOMH6DC3Rd2O0

use Illuminate\Support\Facades\Http;

class GoogleTran
{
    private $api_key = "AIzaSyBm388FnlK_Li_KAYVP-olOMH6DC3Rd2O0";

    protected static $language = [
        "zh" => "zh",        // 中文简体
        "zh_HK" => "zh-TW",     // 中文繁体
        "en" => "en",        // 英文
        "vi" => "vi",        // 越南语
        "id" => "id",        // 印尼语
        "th" => "th",        // 泰语
        "tr" => "tr",        // 土耳其语
        "pt-BR" => "pt-BR",     // 葡萄牙语（巴西）
        "es" => "es",        // 西班牙语（含墨西哥）
        "ja" => "ja",        // 日语
        "ru" => "ru",        // 俄语
        "ko" => "ko",        // 韩语
        "ne" => "ne",        // 尼泊尔语
        "my" => "my",        // 缅甸语
        "bn" => "bn",        // 孟加拉语
        "ur" => "ur",        // 乌尔都语
        "ms" => "ms",        // 马来语
        "fil" => "fil",       // 菲律宾语（Tagalog）
        "hi" => "hi",        // 印地语
    ];

    public function translate($string, $to = '')
    {
        if (!empty($to)) {
            $this->to = $this->checkLanguage($to);
        }
        $response = Http::withOptions([
            'proxy' => [
                'http' => 'http://127.0.0.1:7897',
                'https' => 'http://127.0.0.1:7897',
            ],
        ])->asJson()->post("https://translation.googleapis.com/language/translate/v2?key={$this->api_key}", $this->getQueryData($string));
        if ($response->failed()) {
            throw new \Exception('Google 翻译 API 调用失败：' . $response->body());
        }

        $data = $response->json();
        $translations = array_column($data['data']['translations'], 'translatedText');

        // 返回数组结果
        return $translations;
    }


    private function getQueryData($string)
    {
        $query = [
            'q' => $string,
            'source' => "zh",
            'target' => $this->to,
            'format' => 'text',
        ];
        return $query;
    }


    private function checkLanguage($language)
    {
        if (!isset(self::$language[$language])) {
            throw new \Exception("不支持" . $language . "语言");
        }
        return self::$language[$language];
    }
}
