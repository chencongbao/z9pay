<?php

namespace App\Services\Enums;

class LanguageEnum
{
    public const EN_US = 'en-US';
    public const ES_ES = 'es-ES';
    public const FR_FR = 'fr-FR';
    public const DE_DE = 'de-DE';
    public const PT_BR = 'pt-BR';
    public const PT_PT = 'pt-PT';
    public const RU_RU = 'ru-RU';
    public const AR_SA = 'ar-SA';
    public const HI_IN = 'hi-IN';
    public const BN_BD = 'bn-BD';
    public const UR_PK = 'ur-PK';
    public const TR_TR = 'tr-TR';
    public const FA_IR = 'fa-IR';
    public const ID_ID = 'id-ID';
    public const MS_MY = 'ms-MY';
    public const JA_JP = 'ja-JP';
    public const KO_KR = 'ko-KR';
    public const VI_VN = 'vi-VN';
    public const TH_TH = 'th-TH';
    public const TL_PH = 'tl-PH';
    public const SW_KE = 'sw-KE';
    public const AM_ET = 'am-ET';
    public const HA_NG = 'ha-NG';
    public const YO_NG = 'yo-NG';
    public const IG_NG = 'ig-NG';
    public const IT_IT = 'it-IT';
    public const NL_NL = 'nl-NL';
    public const SV_SE = 'sv-SE';
    public const NO_NO = 'no-NO';
    public const DA_DK = 'da-DK';
    public const FI_FI = 'fi-FI';
    public const PL_PL = 'pl-PL';
    public const CS_CZ = 'cs-CZ';
    public const SK_SK = 'sk-SK';
    public const HU_HU = 'hu-HU';
    public const RO_RO = 'ro-RO';
    public const BG_BG = 'bg-BG';
    public const EL_GR = 'el-GR';
    public const UK_UA = 'uk-UA';
    public const SR_RS = 'sr-RS';
    public const HR_HR = 'hr-HR';
    public const SL_SI = 'sl-SI';
    public const LT_LT = 'lt-LT';
    public const LV_LV = 'lv-LV';
    public const ET_EE = 'et-EE';
    public const HE_IL = 'he-IL';
    public const TA_IN = 'ta-IN';
    public const TE_IN = 'te-IN';
    public const MR_IN = 'mr-IN';
    public const GU_IN = 'gu-IN';
    public const KN_IN = 'kn-IN';
    public const ML_IN = 'ml-IN';
    public const PA_IN = 'pa-IN';

    public const MAP = [
        self::EN_US => '英语',
        self::ES_ES => '西班牙语',
        self::FR_FR => '法语',
        self::DE_DE => '德语',
        self::PT_BR => '葡萄牙语（巴西）',
        self::PT_PT => '葡萄牙语（葡萄牙）',
        self::RU_RU => '俄语',
        self::AR_SA => '阿拉伯语',
        self::HI_IN => '印地语',
        self::BN_BD => '孟加拉语',
        self::UR_PK => '乌尔都语',
        self::TR_TR => '土耳其语',
        self::FA_IR => '波斯语',
        self::ID_ID => '印尼语',
        self::MS_MY => '马来语',
        self::JA_JP => '日语',
        self::KO_KR => '韩语',
        self::VI_VN => '越南语',
        self::TH_TH => '泰语',
        self::TL_PH => '菲律宾语',
        self::SW_KE => '斯瓦希里语',
        self::AM_ET => '阿姆哈拉语',
        self::HA_NG => '豪萨语',
        self::YO_NG => '约鲁巴语',
        self::IG_NG => '伊博语',
        self::IT_IT => '意大利语',
        self::NL_NL => '荷兰语',
        self::SV_SE => '瑞典语',
        self::NO_NO => '挪威语',
        self::DA_DK => '丹麦语',
        self::FI_FI => '芬兰语',
        self::PL_PL => '波兰语',
        self::CS_CZ => '捷克语',
        self::SK_SK => '斯洛伐克语',
        self::HU_HU => '匈牙利语',
        self::RO_RO => '罗马尼亚语',
        self::BG_BG => '保加利亚语',
        self::EL_GR => '希腊语',
        self::UK_UA => '乌克兰语',
        self::SR_RS => '塞尔维亚语',
        self::HR_HR => '克罗地亚语',
        self::SL_SI => '斯洛文尼亚语',
        self::LT_LT => '立陶宛语',
        self::LV_LV => '拉脱维亚语',
        self::ET_EE => '爱沙尼亚语',
        self::HE_IL => '希伯来语',
        self::TA_IN => '泰米尔语',
        self::TE_IN => '泰卢固语',
        self::MR_IN => '马拉地语',
        self::GU_IN => '古吉拉特语',
        self::KN_IN => '卡纳达语',
        self::ML_IN => '马拉雅拉姆语',
        self::PA_IN => '旁遮普语',
    ];

    public static function all(): array
    {
        return self::MAP;
    }

    public static function name(string $code): string
    {
        return self::MAP[$code] ?? 'Unknown';
    }
}
