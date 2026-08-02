<?php

namespace App\Services\Cache;

class CacheConstPrefixService
{
     const MERCHANT_APPKEY_PREFIEX = 'cache_merchant_appkey_';

     const BLACK_CONTENT_IP = 'cache_black_content_ip';

     const BLACK_CONTENT_AREA = "cache_black_content_area";

    const BLACK_CONTENT_DEPOSIT_PAY_NAME = 'cache_black_content_deposit_pay_name';

    const CHANNEL_ACCOUNT_INFO = "cache_channel_account_info_";

    const CHANNEL_CLASSNAME = "cache_channel_classname_";

    const CHANNEL_PAYMENT_CODE_MAP = "cache_channel_coder_";

    const CHANNEL_CALLBACK_WHITE_IP = "cache_channel_ip_classname_";

    const CHANNEL_LIST = "channel_list";

    const CASHIER_USER_BLACK_IP_ADDRESS = "cashier_user_black_ip_address"; //按地区缓存被禁用的ip

    const ADMIN_OPERATE_GOOGLE_2FA_CODE_TIME = "admin_operate_google_2fa_code_time_";

    const CACHE_TELEGRAM_ROBOT_INFO = "cache_telegram_robot_info";

    const CACHE_TELEGRAM_WEBHOOK_SETTING_SUCCESS = 'cache_telegram_webhook_setting_success'; //缓存是否已经设置过webhook

    const CACHE_MERCHANT_BASE_INFO = "cache_merchant_base_info_";

    const DEPOSIT_ORDER_SUM_ACTUAL_AMOUNT = "deposit_order_sum_actual_amount_";

    const DEPOSIT_ORDER_SUM_MERCHANTAGENT1_COMMISSION = "deposit_order_sum_merchantagent1_commission_";

    const DEPOSIT_ORDER_SUM_MERCHANTAGENT2_COMMISSION = "deposit_order_sum_merchantagent2_commission_";

    const DEPOSIT_ORDER_SUM_USERAGENT1_COMMISSION = "deposit_order_sum_useragent1_commission_";

    const DEPOSIT_ORDER_SUM_USERAGENT2_COMMISSION = "deposit_order_sum_useragent2_commission_";

    const DEPOSIT_ORDER_SUM_USERAGENT3_COMMISSION = "deposit_order_sum_useragent3_commission_";


    const TELEGRAM_GROUP_TYPE = "telegram_group_type_";

    const TELEGRAM_GROUP_AND_MERCHAND_USER_ID = "telegram_group_and_merchand_user_id_";

    const TELEGRAM_GROUP_AND_USER_ID = "telegram_group_and_user_id_";

    const DEPOSIT_ORDER_CHSANNEL_AUTO_PRIORITY = "deposit_order_chsannel_auto_priority_";

    const TRANSFER_ORDER_CHSANNEL_AUTO_PRIORITY = "transfer_order_chsannel_auto_priority_";

    const TAIYAPAYMENT_USER_LOGIN_TOKEN = "taiyapayment_user_login_token_";


    const SAME_AMOUNT_INTERVAL_TIME = "same_amount_interval_time_";

    const SEND_CHANNEL_EXCEPTION_NOTICE = "send_channel_exception_notice_";

    const TELEGRAM_LOGIN_EXCEPTION_BAN_INFO = "telegram_login_exception_ban_info_";

    const DEPOSIT_ORDER_INFO = "deposit_order_info_";

    const DEPOSIT_ORDER_CASHIER_INFO = "deposit_order_cashier_info_";

    const TRANSFER_ORDER_INFO = "transfer_order_info_";

    const TELEGRAM_MESSAGE_JIAJI_INFO = "telegram_message_jiaji_info_";

    const DEPOSIT_ORDER_ID_INFO = "deposit_order_id_info_";

    const TRANSFER_ORDER_ID_INFO = "transfer_order_id_info_";

    const RUNS_INFO = "runs_info_";

    const MERCHANT_DEPOSIT_ORDERNUMBER_INFO = "merchant_deposit_ordernumber_info_";

    const MERCHANT_TRANSFER_ORDERNUMBER_INFO = "merchant_transfer_ordernumber_info_";

    const CACHE_DEPOSIT_FIELDS = [
        'id',
        'ordernumber',
        'mid',
        'amount',
        'currency_id',
        'payment_id',
        'order_no',
        'pay_amount',
        'actual_amount',
        'user_id',
        'channel_id',
        'user_bank_id',
        'success_time',
        'remark',
        'status',
        'callback_status',
        'merchant_fee',
        'merchant_extra_fee',
        'created_at',
        'updated_at',
        'channel_ordernumber',
        'channel_info',
        'callback_time',
        'callback_count',
        'pay_name',
        'ip',
        'bank_id',
        'bank_name',
        'card_no',
        'card_pin',
        'card_name',
        'expired_time',
        'utr',
        'uid',
        'account_type',
        'return_url',
        'show_amount',
        'pay_status',
        'pay_certificate',
        'confirm_time',
        'alipay_uid',
        'collection_bank_code',
        'collection_bank_branch',
        'collection_bank_name',
        'collection_card_no',
        'collection_name',
        'collection_app_link',
        'collection_qrcode',
        'collection_qrcode_url',
        'collection_app_info',
        'channel_pay_url',
        'data_type',
        'settlement_mode',
        'settlement_time',
        'hand_success',
        'hand_admin_id',
        'merchant_rate',
        'merchant_agent1_id',
        'merchant_agent1_rate',
        'merchant_agent1_commission',
        'merchant_agent2_id',
        'merchant_agent2_rate',
        'merchant_agent2_commission',
        'merchant_agent3_id',
        'merchant_agent3_rate',
        'merchant_agent3_commission',
        'user_rate',
        'user_commission',
        'user_agent1_id',
        'user_agent1_rate',
        'user_agent1_commission',
        'user_agent2_id',
        'user_agent2_rate',
        'user_agent2_commission',
        'user_agent3_id',
        'user_agent3_rate',
        'user_agent3_commission',
        'user_agent4_id',
        'user_agent4_rate',
        'user_agent4_commission',
        'user_agent5_id',
        'user_agent5_rate',
        'user_agent5_commission',
        'channel_rate',
        'channel_cost',
        'profit',
        'usdt_rate',
        'time',
    ];

    const CACHE_DEPOSIT_FILED = self::CACHE_DEPOSIT_FIELDS;

    const CACHE_TRANSFER_FILED = ['id', 'ordernumber', 'mid', 'amount', 'currency_id', 'order_no', 'actual_amount', 'user_id', 'channel_id', 'success_time', 'remark', 'status', 'callback_status', 'merchant_fee', 'merchant_extra_fee', 'created_at', 'channel_ordernumber','callback_time','card_no','holder_name','ip','bank_id','bank_name','bank_code','bank_branch','type',"identity_no",'utr','merchant_rate'];

    const USER_BANK_AUTO_PRIORITY = "user_bank_auto_priority_";

    const CHANNEL_DETAIL = "channel_info_";

    const TRRONWEB_LISTENING_ADDRESS = "tronweb_listening_address_";

    const TRONWEB_QUERY_ADDRESS_SEND_TIME = "tronweb_address_send_time_";


    const TELEGRAM_DEPOSIT_ORDER_FORWARD = "telegram_deposit_order_forward_";

    const TELEGRAM_DEPOSIT_ORDER_JIAJI_INFO = "telegram_deposit_order_jiaji_info_";

    const TELEGRAM_TRANSFER_ORDER_FORWARD = "telegram_transfer_order_forward_";

    const TELEGRAM_TRANSFER_ORDER_JIAJI_INFO = "telegram_transfer_order_jiaji_info_";

    const MERCHANT_BALANCE_NOTICE = "merchant_balance_notice_";

    const MERCHANT_SIGN_ERROR_NOTICE = "merchant_sign_error_notice_";

    const CHANNEL_BALANCE_NOTICE = "channel_balance_notice_";

    const CHANNEL_BALANCE_QUERY_EXCEPTION_COOLDOWN = "channel_balance_query_exception_cooldown_";

    const CHANNEL_BALANCE_ORDER_QUERY_THROTTLE = "channel_balance_order_query_throttle_";

    const TELEGRAM_CHANNEL_BALANCE_QUERY_LOCK = "telegram_channel_balance_query_lock_";

    const MERCHANT_LIST = "merchant_list";

    const MERCHANT_AGENT_LIST = "merchant_agnet_list";

    const MERCHANT_AGENT_DETAIL = "merchant_agent_detail_";

    const USER_AGENT_LIST = "user_agent_list";

    const USER_LIST = "user_list";

    const USER_DETAIL = "user_detail_";

    const CACHE_USER_FIELD = ['id','name','username','status','level','balance_amount','pid','acquisition_status','collection_limit_min','collection_limit_max','pay_limit_min','pay_limit_max','limit_deposit_paid_number','user_deposit_payment_rate','user_rate','deposit_user_rate','round_times','deleted_at'];

    const CHANNEL_RATE_DETAIL = "channel_rate_detail_";

    const MERCHANT_WHITE_IP_BY_USERNAME = "MERCHANT_USERNAME_CODER_";

    const USER_DEPOSIT_ORDER_DAIFUKUAN_AMOUNT = "user_deposit_order_daifukuan_amount_";

    const USER_TODAY_DEPOSIT_ORDER_TOTAL_AMOUNT = "user_today_deposit_order_total_amount_";

    const USER_TODAY_DEPOSIT_ORDER_TOTAL_INCOME = "user_today_deposit_order_total_income_";

    const USER_TODAY_DEPOSIT_ORDER_TOTAL_NUMBER = "user_today_deposit_order_total_number_";

    const USER_TODAY_TRANSFER_ORDER_TOTAL_AMOUNT = "user_today_transfer_order_total_amount_";

    const USER_TODAY_TRANSFER_ORDER_TOTAL_INCOME = "user_today_transfer_order_total_income_";

    const USER_TODAY_TRANSFER_ORDER_TOTAL_NUMBER = "user_today_transfer_order_total_number_";

    const USER_MONTH_TOTAL_AMOUNT = "user_month_total_amount_";

    const USER_BANK_LIST = "user_bank_list";

    const USER_BANK_DETAIL = "user_bank_detail_";

    const CACHE_USER_BANK_FIELD = ['id','card_no','bank_id','account_type','name','collection_status','user_id'];

    const CACHE_ADMIN_INFO = "cache_admin_info_";

    const ADMIN_SETTING = "admin_setting";

    const USER_DAIFUKUAN_DEPOSIT_ORDER_LIST = "user_daifukuan_deposit_order_list_";

    const USER_DAIFUKUAN_DEPOSIT_ORDER_LIST_LOCK = "user_daifukuan_deposit_order_list_lock_";

    const USER_BANK_TODAY_DEPOSIT_ORDER_TOTAL_NUMBER = "user_bank_today_deposit_order_total_number_";

    // 保留原缓存前缀，避免发布时丢失当天累计金额。
    const USER_BANK_TODAY_DEPOSIT_ORDER_TOTAL_AMOUNT = "user_bank_today_transfer_order_total_amount_";

    const USER_BANK_TODAY_TRANSFER_ORDER_TOTAL_AMOUNT = self::USER_BANK_TODAY_DEPOSIT_ORDER_TOTAL_AMOUNT;

    const ADMIN_DEPOSIT_ORDER_EXPORT_HAS_EXIST = "admin_deposit_order_export_has_exist_";

    const ADMIN_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST = "admin_merchant_balance_log_export_has_exist_";

    const ADMIN_TRANSFER_ORDER_EXPORT_HAS_EXIST = "admin_transfer_order_export_has_exist_";

    const ADMIN_SETTLEMENT_ORDER_EXPORT_HAS_EXIST = "admin_settlement_order_export_has_exist_";

    const ADMIN_FREEZE_ORDER_EXPORT_HAS_EXIST = "admin_freeze_order_export_has_exist_";


    const MERCHANT_DEPOSIT_ORDER_EXPORT_HAS_EXIST = "merchant_deposit_order_export_has_exist_";

    const MERCHANT_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST = "merchant_merchant_balance_log_export_has_exist_";

    const MERCHANT_TRANSFER_ORDER_EXPORT_HAS_EXIST = "merchant_transfer_order_export_has_exist_";

    const MERCHANT_SETTLEMENT_ORDER_EXPORT_HAS_EXIST = "merchant_settlement_order_export_has_exist_";

    const MERCHANT_BANK_CODE_EXPORT_HAS_EXIST = "merchant_bank_code_export_has_exist_";

    const ADMIN_AGENT_BALANCE_LOGS_EXPORT_HAS_EXIST = "admin_agent_balance_logs_export_has_exist_";

    const ADMIN_USER_AGENT_BALANCE_LOGS_EXPORT_HAS_EXIST = "admin_user_agent_balance_logs_export_has_exist_";

    const ADMIN_BANK_CODE_EXPORT_HAS_EXIST = "admin_bank_code_export_has_exist_";

    const ADMIN_MERCHANT_USER_EXPORT_HAS_EXIST = "admin_merchant_user_export_has_exist_";

    const ADMIN_REPORT_MERCHANT_EXPORT_HAS_EXIST = "admin_report_merchant_export_has_exist_";

    const ADMIN_REPORT_CHANNEL_EXPORT_HAS_EXIST = "admin_report_channel_export_has_exist_";

    const AGENT_DEPOSIT_ORDER_EXPORT_HAS_EXIST = "agent_deposit_order_export_has_exist_";

    const AGENT_TRANSFER_ORDER_EXPORT_HAS_EXIST = "agent_transfer_order_export_has_exist_";

    const AGENT_SETTLEMENT_ORDER_EXPORT_HAS_EXIST = "agent_settlement_order_export_has_exist_";

    const AGENT_BALANCE_LOG_EXPORT_HAS_EXIST = "agent_balance_log_export_has_exist_";

    const ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM = "admin:deposit_manual_success:confirm:";

    const ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM_ORDER = "admin:deposit_manual_success:confirm:order:";

    const MERCHANT_PAYMENT_DETAIL_LIST = "merchant_payment_detail_list_";

    const MERCHANT_CHANNEL_DETAIL_LIST = "merchant_channel_detail_list_";

    const MERCHANT_PAYMENT_TRANSFER_RATE = "merchant_payment_transfer_rate_";

    const LISTENING_TRON_ADDRESS_LIST = "listening_tron_address_list";

    const TRANSFER_ORDER_CONFIRM_ACTION = "transfer_order_confirm_action_";

    const USER_ROUND_TIMES  = "user_round_times_";

    const HANDLE_DO_TRANSFER_ACTION = "handle_do_transfer_action_";

}
