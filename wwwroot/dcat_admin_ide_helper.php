<?php

/**
 * A helper file for Dcat Admin, to provide autocomplete information to your IDE
 *
 * This file should not be included in your code, only analyzed by your IDE!
 *
 * @author jqh <841324345@qq.com>
 */
namespace Dcat\Admin {
    use Illuminate\Support\Collection;

    /**
     * @property Grid\Column|Collection batch_uuid
     * @property Grid\Column|Collection causer_id
     * @property Grid\Column|Collection causer_type
     * @property Grid\Column|Collection created_at
     * @property Grid\Column|Collection event
     * @property Grid\Column|Collection id
     * @property Grid\Column|Collection log_name
     * @property Grid\Column|Collection properties
     * @property Grid\Column|Collection subject_id
     * @property Grid\Column|Collection subject_type
     * @property Grid\Column|Collection updated_at
     * @property Grid\Column|Collection detail
     * @property Grid\Column|Collection name
     * @property Grid\Column|Collection type
     * @property Grid\Column|Collection version
     * @property Grid\Column|Collection is_enabled
     * @property Grid\Column|Collection extension
     * @property Grid\Column|Collection icon
     * @property Grid\Column|Collection order
     * @property Grid\Column|Collection parent_id
     * @property Grid\Column|Collection uri
     * @property Grid\Column|Collection app_type
     * @property Grid\Column|Collection input
     * @property Grid\Column|Collection ip
     * @property Grid\Column|Collection method
     * @property Grid\Column|Collection path
     * @property Grid\Column|Collection target_type
     * @property Grid\Column|Collection user_id
     * @property Grid\Column|Collection menu_id
     * @property Grid\Column|Collection permission_id
     * @property Grid\Column|Collection http_method
     * @property Grid\Column|Collection http_path
     * @property Grid\Column|Collection slug
     * @property Grid\Column|Collection role_id
     * @property Grid\Column|Collection value
     * @property Grid\Column|Collection avatar
     * @property Grid\Column|Collection google_two_fa_bind
     * @property Grid\Column|Collection google_two_fa_enable
     * @property Grid\Column|Collection google_two_fa_secret
     * @property Grid\Column|Collection last_login_ip
     * @property Grid\Column|Collection last_login_time
     * @property Grid\Column|Collection login_white_ip
     * @property Grid\Column|Collection password
     * @property Grid\Column|Collection remember_token
     * @property Grid\Column|Collection session_id
     * @property Grid\Column|Collection status
     * @property Grid\Column|Collection username
     * @property Grid\Column|Collection action_agent_id
     * @property Grid\Column|Collection agent_id
     * @property Grid\Column|Collection amount
     * @property Grid\Column|Collection balance_amount
     * @property Grid\Column|Collection deleted_at
     * @property Grid\Column|Collection mid
     * @property Grid\Column|Collection remark
     * @property Grid\Column|Collection type_id
     * @property Grid\Column|Collection child_id
     * @property Grid\Column|Collection level
     * @property Grid\Column|Collection pid
     * @property Grid\Column|Collection code
     * @property Grid\Column|Collection currency_id
     * @property Grid\Column|Collection telegram_group_id
     * @property Grid\Column|Collection chu_total_amount
     * @property Grid\Column|Collection rate
     * @property Grid\Column|Collection rate1
     * @property Grid\Column|Collection ru_total_amount
     * @property Grid\Column|Collection content
     * @property Grid\Column|Collection channel_id
     * @property Grid\Column|Collection collection_max_amount
     * @property Grid\Column|Collection collection_min_amount
     * @property Grid\Column|Collection collection_total_amount
     * @property Grid\Column|Collection debug_logs
     * @property Grid\Column|Collection params
     * @property Grid\Column|Collection pay_max_amount
     * @property Grid\Column|Collection pay_min_amount
     * @property Grid\Column|Collection pay_total_amount
     * @property Grid\Column|Collection public_params
     * @property Grid\Column|Collection secret_params
     * @property Grid\Column|Collection bank_code_id
     * @property Grid\Column|Collection fixed_rate
     * @property Grid\Column|Collection payment_id
     * @property Grid\Column|Collection auto_priority
     * @property Grid\Column|Collection auto_query_status
     * @property Grid\Column|Collection balance_update_time
     * @property Grid\Column|Collection batch_transfer
     * @property Grid\Column|Collection callback_white_ip
     * @property Grid\Column|Collection cashier_payment
     * @property Grid\Column|Collection classname
     * @property Grid\Column|Collection coder
     * @property Grid\Column|Collection currency
     * @property Grid\Column|Collection deposit_order_query
     * @property Grid\Column|Collection is_cashier_on
     * @property Grid\Column|Collection is_json_return
     * @property Grid\Column|Collection is_real_name
     * @property Grid\Column|Collection payment_ids
     * @property Grid\Column|Collection priority
     * @property Grid\Column|Collection telegram_user_id
     * @property Grid\Column|Collection transfer_order_query
     * @property Grid\Column|Collection transfer_payment
     * @property Grid\Column|Collection use_cashier
     * @property Grid\Column|Collection date_add
     * @property Grid\Column|Collection status1_count
     * @property Grid\Column|Collection status2_count
     * @property Grid\Column|Collection status3_count
     * @property Grid\Column|Collection status4_count
     * @property Grid\Column|Collection status5_count
     * @property Grid\Column|Collection status6_count
     * @property Grid\Column|Collection total_amount
     * @property Grid\Column|Collection total_count
     * @property Grid\Column|Collection account_type
     * @property Grid\Column|Collection actual_amount
     * @property Grid\Column|Collection alipay_uid
     * @property Grid\Column|Collection bank
     * @property Grid\Column|Collection bank_code
     * @property Grid\Column|Collection bank_id
     * @property Grid\Column|Collection bank_name
     * @property Grid\Column|Collection callback_count
     * @property Grid\Column|Collection callback_status
     * @property Grid\Column|Collection callback_time
     * @property Grid\Column|Collection card_name
     * @property Grid\Column|Collection card_no
     * @property Grid\Column|Collection channel_account_id
     * @property Grid\Column|Collection channel_cost
     * @property Grid\Column|Collection channel_info
     * @property Grid\Column|Collection channel_ordernumber
     * @property Grid\Column|Collection channel_pay_url
     * @property Grid\Column|Collection channel_rate
     * @property Grid\Column|Collection collection_app_info
     * @property Grid\Column|Collection collection_app_link
     * @property Grid\Column|Collection collection_bank_branch
     * @property Grid\Column|Collection collection_bank_code
     * @property Grid\Column|Collection collection_bank_name
     * @property Grid\Column|Collection collection_card_no
     * @property Grid\Column|Collection collection_name
     * @property Grid\Column|Collection collection_qrcode
     * @property Grid\Column|Collection collection_qrcode_url
     * @property Grid\Column|Collection confirm_time
     * @property Grid\Column|Collection data_type
     * @property Grid\Column|Collection email
     * @property Grid\Column|Collection expired_time
     * @property Grid\Column|Collection extra
     * @property Grid\Column|Collection fee
     * @property Grid\Column|Collection freeze_amount
     * @property Grid\Column|Collection hand_admin_id
     * @property Grid\Column|Collection hand_success
     * @property Grid\Column|Collection hour
     * @property Grid\Column|Collection merchant_agent1_commission
     * @property Grid\Column|Collection merchant_agent1_id
     * @property Grid\Column|Collection merchant_agent1_rate
     * @property Grid\Column|Collection merchant_agent2_commission
     * @property Grid\Column|Collection merchant_agent2_id
     * @property Grid\Column|Collection merchant_agent2_rate
     * @property Grid\Column|Collection merchant_agent3_commission
     * @property Grid\Column|Collection merchant_agent3_id
     * @property Grid\Column|Collection merchant_agent3_rate
     * @property Grid\Column|Collection merchant_extra_fee
     * @property Grid\Column|Collection merchant_fee
     * @property Grid\Column|Collection merchant_rate
     * @property Grid\Column|Collection notify_url
     * @property Grid\Column|Collection order_no
     * @property Grid\Column|Collection order_type
     * @property Grid\Column|Collection ordernumber
     * @property Grid\Column|Collection pay_amount
     * @property Grid\Column|Collection pay_certificate
     * @property Grid\Column|Collection pay_name
     * @property Grid\Column|Collection pay_status
     * @property Grid\Column|Collection phone
     * @property Grid\Column|Collection profit
     * @property Grid\Column|Collection query_message_content
     * @property Grid\Column|Collection return_url
     * @property Grid\Column|Collection settlement_mode
     * @property Grid\Column|Collection settlement_time
     * @property Grid\Column|Collection show_amount
     * @property Grid\Column|Collection success_time
     * @property Grid\Column|Collection tag
     * @property Grid\Column|Collection time
     * @property Grid\Column|Collection true_ip
     * @property Grid\Column|Collection uid
     * @property Grid\Column|Collection usdt_rate
     * @property Grid\Column|Collection user_agent1_commission
     * @property Grid\Column|Collection user_agent1_id
     * @property Grid\Column|Collection user_agent1_rate
     * @property Grid\Column|Collection user_agent2_commission
     * @property Grid\Column|Collection user_agent2_id
     * @property Grid\Column|Collection user_agent2_rate
     * @property Grid\Column|Collection user_agent3_commission
     * @property Grid\Column|Collection user_agent3_id
     * @property Grid\Column|Collection user_agent3_rate
     * @property Grid\Column|Collection user_agent4_commission
     * @property Grid\Column|Collection user_agent4_id
     * @property Grid\Column|Collection user_agent4_rate
     * @property Grid\Column|Collection user_agent5_commission
     * @property Grid\Column|Collection user_agent5_id
     * @property Grid\Column|Collection user_agent5_rate
     * @property Grid\Column|Collection user_bank_id
     * @property Grid\Column|Collection user_commission
     * @property Grid\Column|Collection user_rate
     * @property Grid\Column|Collection utr
     * @property Grid\Column|Collection message
     * @property Grid\Column|Collection order_id
     * @property Grid\Column|Collection connection
     * @property Grid\Column|Collection exception
     * @property Grid\Column|Collection failed_at
     * @property Grid\Column|Collection payload
     * @property Grid\Column|Collection queue
     * @property Grid\Column|Collection uuid
     * @property Grid\Column|Collection deposit_order_id
     * @property Grid\Column|Collection unfreeze_time
     * @property Grid\Column|Collection address
     * @property Grid\Column|Collection chat_id
     * @property Grid\Column|Collection count
     * @property Grid\Column|Collection attempts
     * @property Grid\Column|Collection available_at
     * @property Grid\Column|Collection reserved_at
     * @property Grid\Column|Collection trx_balance
     * @property Grid\Column|Collection usdt_balance
     * @property Grid\Column|Collection deposit_total_amount
     * @property Grid\Column|Collection deposit_total_income
     * @property Grid\Column|Collection total_income
     * @property Grid\Column|Collection transfer_total_amount
     * @property Grid\Column|Collection transfer_total_income
     * @property Grid\Column|Collection account_cny
     * @property Grid\Column|Collection account_usdt
     * @property Grid\Column|Collection account_usdt_rate
     * @property Grid\Column|Collection order_cny
     * @property Grid\Column|Collection order_usdt
     * @property Grid\Column|Collection order_usdt_rate
     * @property Grid\Column|Collection total_cny
     * @property Grid\Column|Collection total_usdt
     * @property Grid\Column|Collection usdt_avg_rate
     * @property Grid\Column|Collection admin_id
     * @property Grid\Column|Collection settlement_amount
     * @property Grid\Column|Collection deposit_fee
     * @property Grid\Column|Collection float_status
     * @property Grid\Column|Collection merchant_user_id
     * @property Grid\Column|Collection agent_user_id
     * @property Grid\Column|Collection amount_float_type
     * @property Grid\Column|Collection appkey
     * @property Grid\Column|Collection appsecret
     * @property Grid\Column|Collection available_balance
     * @property Grid\Column|Collection cashier_domain
     * @property Grid\Column|Collection check_order
     * @property Grid\Column|Collection default_usdt_ava_rate
     * @property Grid\Column|Collection deposit_channel_mode
     * @property Grid\Column|Collection deposits_callback_url
     * @property Grid\Column|Collection float_amount
     * @property Grid\Column|Collection history_balance_amount
     * @property Grid\Column|Collection history_end_balance_amount_time
     * @property Grid\Column|Collection is_need_decimal
     * @property Grid\Column|Collection is_usdt_ava_rate
     * @property Grid\Column|Collection last_balance_amount_time
     * @property Grid\Column|Collection manager_telegram_user_id
     * @property Grid\Column|Collection pay_white_ip
     * @property Grid\Column|Collection sign_space
     * @property Grid\Column|Collection transfer_callback_url
     * @property Grid\Column|Collection transfer_channel_mode
     * @property Grid\Column|Collection usdt_ava_rate
     * @property Grid\Column|Collection usdt_float_rate
     * @property Grid\Column|Collection withdraw_white_ip
     * @property Grid\Column|Collection agent1_rate
     * @property Grid\Column|Collection agent2_rate
     * @property Grid\Column|Collection agent3_rate
     * @property Grid\Column|Collection max_limit_amount
     * @property Grid\Column|Collection min_limit_amount
     * @property Grid\Column|Collection pay_rate
     * @property Grid\Column|Collection transfer_rates
     * @property Grid\Column|Collection action_admin_id
     * @property Grid\Column|Collection amount_password
     * @property Grid\Column|Collection abilities
     * @property Grid\Column|Collection expires_at
     * @property Grid\Column|Collection last_used_at
     * @property Grid\Column|Collection token
     * @property Grid\Column|Collection tokenable_id
     * @property Grid\Column|Collection tokenable_type
     * @property Grid\Column|Collection cid
     * @property Grid\Column|Collection deposit_order_number_fail
     * @property Grid\Column|Collection deposit_order_number_overtime
     * @property Grid\Column|Collection deposit_order_number_success
     * @property Grid\Column|Collection deposit_order_number_swiping
     * @property Grid\Column|Collection deposit_order_number_total
     * @property Grid\Column|Collection deposit_order_total_amount
     * @property Grid\Column|Collection deposit_order_total_fee
     * @property Grid\Column|Collection deposit_profit
     * @property Grid\Column|Collection settlement_order_number_fail
     * @property Grid\Column|Collection settlement_order_number_success
     * @property Grid\Column|Collection settlement_order_number_total
     * @property Grid\Column|Collection settlement_order_total_amount
     * @property Grid\Column|Collection settlement_order_total_fee
     * @property Grid\Column|Collection settlement_profit
     * @property Grid\Column|Collection transfer_order_number_fail
     * @property Grid\Column|Collection transfer_order_number_success
     * @property Grid\Column|Collection transfer_order_number_total
     * @property Grid\Column|Collection transfer_order_total_amount
     * @property Grid\Column|Collection transfer_order_total_fee
     * @property Grid\Column|Collection transfer_profit
     * @property Grid\Column|Collection add_total_amount
     * @property Grid\Column|Collection aid
     * @property Grid\Column|Collection deposit_commission
     * @property Grid\Column|Collection jian_total_amount
     * @property Grid\Column|Collection settlement_commission
     * @property Grid\Column|Collection transfer_commission
     * @property Grid\Column|Collection deposit_one_agent_commission
     * @property Grid\Column|Collection deposit_three_agent_commission
     * @property Grid\Column|Collection deposit_two_agent_commission
     * @property Grid\Column|Collection settlement_one_agent_commission
     * @property Grid\Column|Collection settlement_three_agent_commission
     * @property Grid\Column|Collection settlement_two_agent_commission
     * @property Grid\Column|Collection transfer_one_agent_commission
     * @property Grid\Column|Collection transfer_three_agent_commission
     * @property Grid\Column|Collection transfer_two_agent_commission
     * @property Grid\Column|Collection ubid
     * @property Grid\Column|Collection commission_add_total_amount
     * @property Grid\Column|Collection commission_jian_total_amount
     * @property Grid\Column|Collection deposit_add_total_amount
     * @property Grid\Column|Collection deposit_five_agent_commission
     * @property Grid\Column|Collection deposit_four_agent_commission
     * @property Grid\Column|Collection deposit_jian_total_amount
     * @property Grid\Column|Collection settlement_five_agent_commission
     * @property Grid\Column|Collection settlement_four_agent_commission
     * @property Grid\Column|Collection transfer_add_total_amount
     * @property Grid\Column|Collection transfer_five_agent_commission
     * @property Grid\Column|Collection transfer_four_agent_commission
     * @property Grid\Column|Collection transfer_jian_total_amount
     * @property Grid\Column|Collection data_content
     * @property Grid\Column|Collection admin_action_id
     * @property Grid\Column|Collection bank_branch
     * @property Grid\Column|Collection bank_city
     * @property Grid\Column|Collection bank_mobile
     * @property Grid\Column|Collection bank_province
     * @property Grid\Column|Collection callToken
     * @property Grid\Column|Collection child_count
     * @property Grid\Column|Collection holder_name
     * @property Grid\Column|Collection identity_no
     * @property Grid\Column|Collection merchant_action_id
     * @property Grid\Column|Collection pay_certificate_1
     * @property Grid\Column|Collection pay_certificate_2
     * @property Grid\Column|Collection pay_certificate_3
     * @property Grid\Column|Collection resetpay_number
     * @property Grid\Column|Collection withdrawQueryUrl
     * @property Grid\Column|Collection action_user_id
     * @property Grid\Column|Collection is_agent
     * @property Grid\Column|Collection type_balance_amount
     * @property Grid\Column|Collection action
     * @property Grid\Column|Collection collection_status
     * @property Grid\Column|Collection doing_status
     * @property Grid\Column|Collection is_mobile_bank
     * @property Grid\Column|Collection limint_day_amount
     * @property Grid\Column|Collection limint_max_amount
     * @property Grid\Column|Collection limint_min_amount
     * @property Grid\Column|Collection limit_day_order_number
     * @property Grid\Column|Collection merchant_user_ids
     * @property Grid\Column|Collection payment_qrcode
     * @property Grid\Column|Collection payment_qrcode_url
     * @property Grid\Column|Collection same_amount_interval_time
     * @property Grid\Column|Collection extra_user_ids
     * @property Grid\Column|Collection specialized_merchant_user_ids
     * @property Grid\Column|Collection commission
     * @property Grid\Column|Collection account_types
     * @property Grid\Column|Collection acquisition_status
     * @property Grid\Column|Collection action_amount
     * @property Grid\Column|Collection action_collection_status
     * @property Grid\Column|Collection action_delete
     * @property Grid\Column|Collection action_limit_card
     * @property Grid\Column|Collection action_method
     * @property Grid\Column|Collection admin_user_id
     * @property Grid\Column|Collection agent4_rate
     * @property Grid\Column|Collection agent5_rate
     * @property Grid\Column|Collection auto_refresh
     * @property Grid\Column|Collection collection_group_merchant_ids
     * @property Grid\Column|Collection collection_limit_max
     * @property Grid\Column|Collection collection_limit_min
     * @property Grid\Column|Collection commission_balance_amount
     * @property Grid\Column|Collection deposit_agent1_rate
     * @property Grid\Column|Collection deposit_agent2_rate
     * @property Grid\Column|Collection deposit_agent3_rate
     * @property Grid\Column|Collection deposit_agent4_rate
     * @property Grid\Column|Collection deposit_agent5_rate
     * @property Grid\Column|Collection deposit_amount
     * @property Grid\Column|Collection deposit_balance_amount
     * @property Grid\Column|Collection deposit_notice
     * @property Grid\Column|Collection deposit_user_rate
     * @property Grid\Column|Collection limit_deposit_paid_number
     * @property Grid\Column|Collection lock_user
     * @property Grid\Column|Collection mobile
     * @property Grid\Column|Collection pay_group_merchant_user_ids
     * @property Grid\Column|Collection pay_limit_max
     * @property Grid\Column|Collection pay_limit_min
     * @property Grid\Column|Collection self_add_bank
     * @property Grid\Column|Collection settlement_agent1_rate
     * @property Grid\Column|Collection settlement_agent2_rate
     * @property Grid\Column|Collection settlement_agent3_rate
     * @property Grid\Column|Collection settlement_agent4_rate
     * @property Grid\Column|Collection settlement_agent5_rate
     * @property Grid\Column|Collection settlement_user_rate
     * @property Grid\Column|Collection transfer_agent1_rate
     * @property Grid\Column|Collection transfer_agent2_rate
     * @property Grid\Column|Collection transfer_agent3_rate
     * @property Grid\Column|Collection transfer_agent4_rate
     * @property Grid\Column|Collection transfer_agent5_rate
     * @property Grid\Column|Collection transfer_balance_amount
     * @property Grid\Column|Collection transfer_notice
     * @property Grid\Column|Collection transfer_user_rate
     * @property Grid\Column|Collection user_group_id
     * @property Grid\Column|Collection zeros_balance
     * @property Grid\Column|Collection api_messages_count
     * @property Grid\Column|Collection app_id
     * @property Grid\Column|Collection peak_connections_count
     * @property Grid\Column|Collection websocket_messages_count
     *
     * @method Grid\Column|Collection batch_uuid(string $label = null)
     * @method Grid\Column|Collection causer_id(string $label = null)
     * @method Grid\Column|Collection causer_type(string $label = null)
     * @method Grid\Column|Collection created_at(string $label = null)
     * @method Grid\Column|Collection event(string $label = null)
     * @method Grid\Column|Collection id(string $label = null)
     * @method Grid\Column|Collection log_name(string $label = null)
     * @method Grid\Column|Collection properties(string $label = null)
     * @method Grid\Column|Collection subject_id(string $label = null)
     * @method Grid\Column|Collection subject_type(string $label = null)
     * @method Grid\Column|Collection updated_at(string $label = null)
     * @method Grid\Column|Collection detail(string $label = null)
     * @method Grid\Column|Collection name(string $label = null)
     * @method Grid\Column|Collection type(string $label = null)
     * @method Grid\Column|Collection version(string $label = null)
     * @method Grid\Column|Collection is_enabled(string $label = null)
     * @method Grid\Column|Collection extension(string $label = null)
     * @method Grid\Column|Collection icon(string $label = null)
     * @method Grid\Column|Collection order(string $label = null)
     * @method Grid\Column|Collection parent_id(string $label = null)
     * @method Grid\Column|Collection uri(string $label = null)
     * @method Grid\Column|Collection app_type(string $label = null)
     * @method Grid\Column|Collection input(string $label = null)
     * @method Grid\Column|Collection ip(string $label = null)
     * @method Grid\Column|Collection method(string $label = null)
     * @method Grid\Column|Collection path(string $label = null)
     * @method Grid\Column|Collection target_type(string $label = null)
     * @method Grid\Column|Collection user_id(string $label = null)
     * @method Grid\Column|Collection menu_id(string $label = null)
     * @method Grid\Column|Collection permission_id(string $label = null)
     * @method Grid\Column|Collection http_method(string $label = null)
     * @method Grid\Column|Collection http_path(string $label = null)
     * @method Grid\Column|Collection slug(string $label = null)
     * @method Grid\Column|Collection role_id(string $label = null)
     * @method Grid\Column|Collection value(string $label = null)
     * @method Grid\Column|Collection avatar(string $label = null)
     * @method Grid\Column|Collection google_two_fa_bind(string $label = null)
     * @method Grid\Column|Collection google_two_fa_enable(string $label = null)
     * @method Grid\Column|Collection google_two_fa_secret(string $label = null)
     * @method Grid\Column|Collection last_login_ip(string $label = null)
     * @method Grid\Column|Collection last_login_time(string $label = null)
     * @method Grid\Column|Collection login_white_ip(string $label = null)
     * @method Grid\Column|Collection password(string $label = null)
     * @method Grid\Column|Collection remember_token(string $label = null)
     * @method Grid\Column|Collection session_id(string $label = null)
     * @method Grid\Column|Collection status(string $label = null)
     * @method Grid\Column|Collection username(string $label = null)
     * @method Grid\Column|Collection action_agent_id(string $label = null)
     * @method Grid\Column|Collection agent_id(string $label = null)
     * @method Grid\Column|Collection amount(string $label = null)
     * @method Grid\Column|Collection balance_amount(string $label = null)
     * @method Grid\Column|Collection deleted_at(string $label = null)
     * @method Grid\Column|Collection mid(string $label = null)
     * @method Grid\Column|Collection remark(string $label = null)
     * @method Grid\Column|Collection type_id(string $label = null)
     * @method Grid\Column|Collection child_id(string $label = null)
     * @method Grid\Column|Collection level(string $label = null)
     * @method Grid\Column|Collection pid(string $label = null)
     * @method Grid\Column|Collection code(string $label = null)
     * @method Grid\Column|Collection currency_id(string $label = null)
     * @method Grid\Column|Collection telegram_group_id(string $label = null)
     * @method Grid\Column|Collection chu_total_amount(string $label = null)
     * @method Grid\Column|Collection rate(string $label = null)
     * @method Grid\Column|Collection rate1(string $label = null)
     * @method Grid\Column|Collection ru_total_amount(string $label = null)
     * @method Grid\Column|Collection content(string $label = null)
     * @method Grid\Column|Collection channel_id(string $label = null)
     * @method Grid\Column|Collection collection_max_amount(string $label = null)
     * @method Grid\Column|Collection collection_min_amount(string $label = null)
     * @method Grid\Column|Collection collection_total_amount(string $label = null)
     * @method Grid\Column|Collection debug_logs(string $label = null)
     * @method Grid\Column|Collection params(string $label = null)
     * @method Grid\Column|Collection pay_max_amount(string $label = null)
     * @method Grid\Column|Collection pay_min_amount(string $label = null)
     * @method Grid\Column|Collection pay_total_amount(string $label = null)
     * @method Grid\Column|Collection public_params(string $label = null)
     * @method Grid\Column|Collection secret_params(string $label = null)
     * @method Grid\Column|Collection bank_code_id(string $label = null)
     * @method Grid\Column|Collection fixed_rate(string $label = null)
     * @method Grid\Column|Collection payment_id(string $label = null)
     * @method Grid\Column|Collection auto_priority(string $label = null)
     * @method Grid\Column|Collection auto_query_status(string $label = null)
     * @method Grid\Column|Collection balance_update_time(string $label = null)
     * @method Grid\Column|Collection batch_transfer(string $label = null)
     * @method Grid\Column|Collection callback_white_ip(string $label = null)
     * @method Grid\Column|Collection cashier_payment(string $label = null)
     * @method Grid\Column|Collection classname(string $label = null)
     * @method Grid\Column|Collection coder(string $label = null)
     * @method Grid\Column|Collection currency(string $label = null)
     * @method Grid\Column|Collection deposit_order_query(string $label = null)
     * @method Grid\Column|Collection is_cashier_on(string $label = null)
     * @method Grid\Column|Collection is_json_return(string $label = null)
     * @method Grid\Column|Collection is_real_name(string $label = null)
     * @method Grid\Column|Collection payment_ids(string $label = null)
     * @method Grid\Column|Collection priority(string $label = null)
     * @method Grid\Column|Collection telegram_user_id(string $label = null)
     * @method Grid\Column|Collection transfer_order_query(string $label = null)
     * @method Grid\Column|Collection transfer_payment(string $label = null)
     * @method Grid\Column|Collection use_cashier(string $label = null)
     * @method Grid\Column|Collection date_add(string $label = null)
     * @method Grid\Column|Collection status1_count(string $label = null)
     * @method Grid\Column|Collection status2_count(string $label = null)
     * @method Grid\Column|Collection status3_count(string $label = null)
     * @method Grid\Column|Collection status4_count(string $label = null)
     * @method Grid\Column|Collection status5_count(string $label = null)
     * @method Grid\Column|Collection status6_count(string $label = null)
     * @method Grid\Column|Collection total_amount(string $label = null)
     * @method Grid\Column|Collection total_count(string $label = null)
     * @method Grid\Column|Collection account_type(string $label = null)
     * @method Grid\Column|Collection actual_amount(string $label = null)
     * @method Grid\Column|Collection alipay_uid(string $label = null)
     * @method Grid\Column|Collection bank(string $label = null)
     * @method Grid\Column|Collection bank_code(string $label = null)
     * @method Grid\Column|Collection bank_id(string $label = null)
     * @method Grid\Column|Collection bank_name(string $label = null)
     * @method Grid\Column|Collection callback_count(string $label = null)
     * @method Grid\Column|Collection callback_status(string $label = null)
     * @method Grid\Column|Collection callback_time(string $label = null)
     * @method Grid\Column|Collection card_name(string $label = null)
     * @method Grid\Column|Collection card_no(string $label = null)
     * @method Grid\Column|Collection channel_account_id(string $label = null)
     * @method Grid\Column|Collection channel_cost(string $label = null)
     * @method Grid\Column|Collection channel_info(string $label = null)
     * @method Grid\Column|Collection channel_ordernumber(string $label = null)
     * @method Grid\Column|Collection channel_pay_url(string $label = null)
     * @method Grid\Column|Collection channel_rate(string $label = null)
     * @method Grid\Column|Collection collection_app_info(string $label = null)
     * @method Grid\Column|Collection collection_app_link(string $label = null)
     * @method Grid\Column|Collection collection_bank_branch(string $label = null)
     * @method Grid\Column|Collection collection_bank_code(string $label = null)
     * @method Grid\Column|Collection collection_bank_name(string $label = null)
     * @method Grid\Column|Collection collection_card_no(string $label = null)
     * @method Grid\Column|Collection collection_name(string $label = null)
     * @method Grid\Column|Collection collection_qrcode(string $label = null)
     * @method Grid\Column|Collection collection_qrcode_url(string $label = null)
     * @method Grid\Column|Collection confirm_time(string $label = null)
     * @method Grid\Column|Collection data_type(string $label = null)
     * @method Grid\Column|Collection email(string $label = null)
     * @method Grid\Column|Collection expired_time(string $label = null)
     * @method Grid\Column|Collection extra(string $label = null)
     * @method Grid\Column|Collection fee(string $label = null)
     * @method Grid\Column|Collection freeze_amount(string $label = null)
     * @method Grid\Column|Collection hand_admin_id(string $label = null)
     * @method Grid\Column|Collection hand_success(string $label = null)
     * @method Grid\Column|Collection hour(string $label = null)
     * @method Grid\Column|Collection merchant_agent1_commission(string $label = null)
     * @method Grid\Column|Collection merchant_agent1_id(string $label = null)
     * @method Grid\Column|Collection merchant_agent1_rate(string $label = null)
     * @method Grid\Column|Collection merchant_agent2_commission(string $label = null)
     * @method Grid\Column|Collection merchant_agent2_id(string $label = null)
     * @method Grid\Column|Collection merchant_agent2_rate(string $label = null)
     * @method Grid\Column|Collection merchant_agent3_commission(string $label = null)
     * @method Grid\Column|Collection merchant_agent3_id(string $label = null)
     * @method Grid\Column|Collection merchant_agent3_rate(string $label = null)
     * @method Grid\Column|Collection merchant_extra_fee(string $label = null)
     * @method Grid\Column|Collection merchant_fee(string $label = null)
     * @method Grid\Column|Collection merchant_rate(string $label = null)
     * @method Grid\Column|Collection notify_url(string $label = null)
     * @method Grid\Column|Collection order_no(string $label = null)
     * @method Grid\Column|Collection order_type(string $label = null)
     * @method Grid\Column|Collection ordernumber(string $label = null)
     * @method Grid\Column|Collection pay_amount(string $label = null)
     * @method Grid\Column|Collection pay_certificate(string $label = null)
     * @method Grid\Column|Collection pay_name(string $label = null)
     * @method Grid\Column|Collection pay_status(string $label = null)
     * @method Grid\Column|Collection phone(string $label = null)
     * @method Grid\Column|Collection profit(string $label = null)
     * @method Grid\Column|Collection query_message_content(string $label = null)
     * @method Grid\Column|Collection return_url(string $label = null)
     * @method Grid\Column|Collection settlement_mode(string $label = null)
     * @method Grid\Column|Collection settlement_time(string $label = null)
     * @method Grid\Column|Collection show_amount(string $label = null)
     * @method Grid\Column|Collection success_time(string $label = null)
     * @method Grid\Column|Collection tag(string $label = null)
     * @method Grid\Column|Collection time(string $label = null)
     * @method Grid\Column|Collection true_ip(string $label = null)
     * @method Grid\Column|Collection uid(string $label = null)
     * @method Grid\Column|Collection usdt_rate(string $label = null)
     * @method Grid\Column|Collection user_agent1_commission(string $label = null)
     * @method Grid\Column|Collection user_agent1_id(string $label = null)
     * @method Grid\Column|Collection user_agent1_rate(string $label = null)
     * @method Grid\Column|Collection user_agent2_commission(string $label = null)
     * @method Grid\Column|Collection user_agent2_id(string $label = null)
     * @method Grid\Column|Collection user_agent2_rate(string $label = null)
     * @method Grid\Column|Collection user_agent3_commission(string $label = null)
     * @method Grid\Column|Collection user_agent3_id(string $label = null)
     * @method Grid\Column|Collection user_agent3_rate(string $label = null)
     * @method Grid\Column|Collection user_agent4_commission(string $label = null)
     * @method Grid\Column|Collection user_agent4_id(string $label = null)
     * @method Grid\Column|Collection user_agent4_rate(string $label = null)
     * @method Grid\Column|Collection user_agent5_commission(string $label = null)
     * @method Grid\Column|Collection user_agent5_id(string $label = null)
     * @method Grid\Column|Collection user_agent5_rate(string $label = null)
     * @method Grid\Column|Collection user_bank_id(string $label = null)
     * @method Grid\Column|Collection user_commission(string $label = null)
     * @method Grid\Column|Collection user_rate(string $label = null)
     * @method Grid\Column|Collection utr(string $label = null)
     * @method Grid\Column|Collection message(string $label = null)
     * @method Grid\Column|Collection order_id(string $label = null)
     * @method Grid\Column|Collection connection(string $label = null)
     * @method Grid\Column|Collection exception(string $label = null)
     * @method Grid\Column|Collection failed_at(string $label = null)
     * @method Grid\Column|Collection payload(string $label = null)
     * @method Grid\Column|Collection queue(string $label = null)
     * @method Grid\Column|Collection uuid(string $label = null)
     * @method Grid\Column|Collection deposit_order_id(string $label = null)
     * @method Grid\Column|Collection unfreeze_time(string $label = null)
     * @method Grid\Column|Collection address(string $label = null)
     * @method Grid\Column|Collection chat_id(string $label = null)
     * @method Grid\Column|Collection count(string $label = null)
     * @method Grid\Column|Collection attempts(string $label = null)
     * @method Grid\Column|Collection available_at(string $label = null)
     * @method Grid\Column|Collection reserved_at(string $label = null)
     * @method Grid\Column|Collection trx_balance(string $label = null)
     * @method Grid\Column|Collection usdt_balance(string $label = null)
     * @method Grid\Column|Collection deposit_total_amount(string $label = null)
     * @method Grid\Column|Collection deposit_total_income(string $label = null)
     * @method Grid\Column|Collection total_income(string $label = null)
     * @method Grid\Column|Collection transfer_total_amount(string $label = null)
     * @method Grid\Column|Collection transfer_total_income(string $label = null)
     * @method Grid\Column|Collection account_cny(string $label = null)
     * @method Grid\Column|Collection account_usdt(string $label = null)
     * @method Grid\Column|Collection account_usdt_rate(string $label = null)
     * @method Grid\Column|Collection order_cny(string $label = null)
     * @method Grid\Column|Collection order_usdt(string $label = null)
     * @method Grid\Column|Collection order_usdt_rate(string $label = null)
     * @method Grid\Column|Collection total_cny(string $label = null)
     * @method Grid\Column|Collection total_usdt(string $label = null)
     * @method Grid\Column|Collection usdt_avg_rate(string $label = null)
     * @method Grid\Column|Collection admin_id(string $label = null)
     * @method Grid\Column|Collection settlement_amount(string $label = null)
     * @method Grid\Column|Collection deposit_fee(string $label = null)
     * @method Grid\Column|Collection float_status(string $label = null)
     * @method Grid\Column|Collection merchant_user_id(string $label = null)
     * @method Grid\Column|Collection agent_user_id(string $label = null)
     * @method Grid\Column|Collection amount_float_type(string $label = null)
     * @method Grid\Column|Collection appkey(string $label = null)
     * @method Grid\Column|Collection appsecret(string $label = null)
     * @method Grid\Column|Collection available_balance(string $label = null)
     * @method Grid\Column|Collection cashier_domain(string $label = null)
     * @method Grid\Column|Collection check_order(string $label = null)
     * @method Grid\Column|Collection default_usdt_ava_rate(string $label = null)
     * @method Grid\Column|Collection deposit_channel_mode(string $label = null)
     * @method Grid\Column|Collection deposits_callback_url(string $label = null)
     * @method Grid\Column|Collection float_amount(string $label = null)
     * @method Grid\Column|Collection history_balance_amount(string $label = null)
     * @method Grid\Column|Collection history_end_balance_amount_time(string $label = null)
     * @method Grid\Column|Collection is_need_decimal(string $label = null)
     * @method Grid\Column|Collection is_usdt_ava_rate(string $label = null)
     * @method Grid\Column|Collection last_balance_amount_time(string $label = null)
     * @method Grid\Column|Collection manager_telegram_user_id(string $label = null)
     * @method Grid\Column|Collection pay_white_ip(string $label = null)
     * @method Grid\Column|Collection sign_space(string $label = null)
     * @method Grid\Column|Collection transfer_callback_url(string $label = null)
     * @method Grid\Column|Collection transfer_channel_mode(string $label = null)
     * @method Grid\Column|Collection usdt_ava_rate(string $label = null)
     * @method Grid\Column|Collection usdt_float_rate(string $label = null)
     * @method Grid\Column|Collection withdraw_white_ip(string $label = null)
     * @method Grid\Column|Collection agent1_rate(string $label = null)
     * @method Grid\Column|Collection agent2_rate(string $label = null)
     * @method Grid\Column|Collection agent3_rate(string $label = null)
     * @method Grid\Column|Collection max_limit_amount(string $label = null)
     * @method Grid\Column|Collection min_limit_amount(string $label = null)
     * @method Grid\Column|Collection pay_rate(string $label = null)
     * @method Grid\Column|Collection transfer_rates(string $label = null)
     * @method Grid\Column|Collection action_admin_id(string $label = null)
     * @method Grid\Column|Collection amount_password(string $label = null)
     * @method Grid\Column|Collection abilities(string $label = null)
     * @method Grid\Column|Collection expires_at(string $label = null)
     * @method Grid\Column|Collection last_used_at(string $label = null)
     * @method Grid\Column|Collection token(string $label = null)
     * @method Grid\Column|Collection tokenable_id(string $label = null)
     * @method Grid\Column|Collection tokenable_type(string $label = null)
     * @method Grid\Column|Collection cid(string $label = null)
     * @method Grid\Column|Collection deposit_order_number_fail(string $label = null)
     * @method Grid\Column|Collection deposit_order_number_overtime(string $label = null)
     * @method Grid\Column|Collection deposit_order_number_success(string $label = null)
     * @method Grid\Column|Collection deposit_order_number_swiping(string $label = null)
     * @method Grid\Column|Collection deposit_order_number_total(string $label = null)
     * @method Grid\Column|Collection deposit_order_total_amount(string $label = null)
     * @method Grid\Column|Collection deposit_order_total_fee(string $label = null)
     * @method Grid\Column|Collection deposit_profit(string $label = null)
     * @method Grid\Column|Collection settlement_order_number_fail(string $label = null)
     * @method Grid\Column|Collection settlement_order_number_success(string $label = null)
     * @method Grid\Column|Collection settlement_order_number_total(string $label = null)
     * @method Grid\Column|Collection settlement_order_total_amount(string $label = null)
     * @method Grid\Column|Collection settlement_order_total_fee(string $label = null)
     * @method Grid\Column|Collection settlement_profit(string $label = null)
     * @method Grid\Column|Collection transfer_order_number_fail(string $label = null)
     * @method Grid\Column|Collection transfer_order_number_success(string $label = null)
     * @method Grid\Column|Collection transfer_order_number_total(string $label = null)
     * @method Grid\Column|Collection transfer_order_total_amount(string $label = null)
     * @method Grid\Column|Collection transfer_order_total_fee(string $label = null)
     * @method Grid\Column|Collection transfer_profit(string $label = null)
     * @method Grid\Column|Collection add_total_amount(string $label = null)
     * @method Grid\Column|Collection aid(string $label = null)
     * @method Grid\Column|Collection deposit_commission(string $label = null)
     * @method Grid\Column|Collection jian_total_amount(string $label = null)
     * @method Grid\Column|Collection settlement_commission(string $label = null)
     * @method Grid\Column|Collection transfer_commission(string $label = null)
     * @method Grid\Column|Collection deposit_one_agent_commission(string $label = null)
     * @method Grid\Column|Collection deposit_three_agent_commission(string $label = null)
     * @method Grid\Column|Collection deposit_two_agent_commission(string $label = null)
     * @method Grid\Column|Collection settlement_one_agent_commission(string $label = null)
     * @method Grid\Column|Collection settlement_three_agent_commission(string $label = null)
     * @method Grid\Column|Collection settlement_two_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_one_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_three_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_two_agent_commission(string $label = null)
     * @method Grid\Column|Collection ubid(string $label = null)
     * @method Grid\Column|Collection commission_add_total_amount(string $label = null)
     * @method Grid\Column|Collection commission_jian_total_amount(string $label = null)
     * @method Grid\Column|Collection deposit_add_total_amount(string $label = null)
     * @method Grid\Column|Collection deposit_five_agent_commission(string $label = null)
     * @method Grid\Column|Collection deposit_four_agent_commission(string $label = null)
     * @method Grid\Column|Collection deposit_jian_total_amount(string $label = null)
     * @method Grid\Column|Collection settlement_five_agent_commission(string $label = null)
     * @method Grid\Column|Collection settlement_four_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_add_total_amount(string $label = null)
     * @method Grid\Column|Collection transfer_five_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_four_agent_commission(string $label = null)
     * @method Grid\Column|Collection transfer_jian_total_amount(string $label = null)
     * @method Grid\Column|Collection data_content(string $label = null)
     * @method Grid\Column|Collection admin_action_id(string $label = null)
     * @method Grid\Column|Collection bank_branch(string $label = null)
     * @method Grid\Column|Collection bank_city(string $label = null)
     * @method Grid\Column|Collection bank_mobile(string $label = null)
     * @method Grid\Column|Collection bank_province(string $label = null)
     * @method Grid\Column|Collection callToken(string $label = null)
     * @method Grid\Column|Collection child_count(string $label = null)
     * @method Grid\Column|Collection holder_name(string $label = null)
     * @method Grid\Column|Collection identity_no(string $label = null)
     * @method Grid\Column|Collection merchant_action_id(string $label = null)
     * @method Grid\Column|Collection pay_certificate_1(string $label = null)
     * @method Grid\Column|Collection pay_certificate_2(string $label = null)
     * @method Grid\Column|Collection pay_certificate_3(string $label = null)
     * @method Grid\Column|Collection resetpay_number(string $label = null)
     * @method Grid\Column|Collection withdrawQueryUrl(string $label = null)
     * @method Grid\Column|Collection action_user_id(string $label = null)
     * @method Grid\Column|Collection is_agent(string $label = null)
     * @method Grid\Column|Collection type_balance_amount(string $label = null)
     * @method Grid\Column|Collection action(string $label = null)
     * @method Grid\Column|Collection collection_status(string $label = null)
     * @method Grid\Column|Collection doing_status(string $label = null)
     * @method Grid\Column|Collection is_mobile_bank(string $label = null)
     * @method Grid\Column|Collection limint_day_amount(string $label = null)
     * @method Grid\Column|Collection limint_max_amount(string $label = null)
     * @method Grid\Column|Collection limint_min_amount(string $label = null)
     * @method Grid\Column|Collection limit_day_order_number(string $label = null)
     * @method Grid\Column|Collection merchant_user_ids(string $label = null)
     * @method Grid\Column|Collection payment_qrcode(string $label = null)
     * @method Grid\Column|Collection payment_qrcode_url(string $label = null)
     * @method Grid\Column|Collection same_amount_interval_time(string $label = null)
     * @method Grid\Column|Collection extra_user_ids(string $label = null)
     * @method Grid\Column|Collection specialized_merchant_user_ids(string $label = null)
     * @method Grid\Column|Collection commission(string $label = null)
     * @method Grid\Column|Collection account_types(string $label = null)
     * @method Grid\Column|Collection acquisition_status(string $label = null)
     * @method Grid\Column|Collection action_amount(string $label = null)
     * @method Grid\Column|Collection action_collection_status(string $label = null)
     * @method Grid\Column|Collection action_delete(string $label = null)
     * @method Grid\Column|Collection action_limit_card(string $label = null)
     * @method Grid\Column|Collection action_method(string $label = null)
     * @method Grid\Column|Collection admin_user_id(string $label = null)
     * @method Grid\Column|Collection agent4_rate(string $label = null)
     * @method Grid\Column|Collection agent5_rate(string $label = null)
     * @method Grid\Column|Collection auto_refresh(string $label = null)
     * @method Grid\Column|Collection collection_group_merchant_ids(string $label = null)
     * @method Grid\Column|Collection collection_limit_max(string $label = null)
     * @method Grid\Column|Collection collection_limit_min(string $label = null)
     * @method Grid\Column|Collection commission_balance_amount(string $label = null)
     * @method Grid\Column|Collection deposit_agent1_rate(string $label = null)
     * @method Grid\Column|Collection deposit_agent2_rate(string $label = null)
     * @method Grid\Column|Collection deposit_agent3_rate(string $label = null)
     * @method Grid\Column|Collection deposit_agent4_rate(string $label = null)
     * @method Grid\Column|Collection deposit_agent5_rate(string $label = null)
     * @method Grid\Column|Collection deposit_amount(string $label = null)
     * @method Grid\Column|Collection deposit_balance_amount(string $label = null)
     * @method Grid\Column|Collection deposit_notice(string $label = null)
     * @method Grid\Column|Collection deposit_user_rate(string $label = null)
     * @method Grid\Column|Collection limit_deposit_paid_number(string $label = null)
     * @method Grid\Column|Collection lock_user(string $label = null)
     * @method Grid\Column|Collection mobile(string $label = null)
     * @method Grid\Column|Collection pay_group_merchant_user_ids(string $label = null)
     * @method Grid\Column|Collection pay_limit_max(string $label = null)
     * @method Grid\Column|Collection pay_limit_min(string $label = null)
     * @method Grid\Column|Collection self_add_bank(string $label = null)
     * @method Grid\Column|Collection settlement_agent1_rate(string $label = null)
     * @method Grid\Column|Collection settlement_agent2_rate(string $label = null)
     * @method Grid\Column|Collection settlement_agent3_rate(string $label = null)
     * @method Grid\Column|Collection settlement_agent4_rate(string $label = null)
     * @method Grid\Column|Collection settlement_agent5_rate(string $label = null)
     * @method Grid\Column|Collection settlement_user_rate(string $label = null)
     * @method Grid\Column|Collection transfer_agent1_rate(string $label = null)
     * @method Grid\Column|Collection transfer_agent2_rate(string $label = null)
     * @method Grid\Column|Collection transfer_agent3_rate(string $label = null)
     * @method Grid\Column|Collection transfer_agent4_rate(string $label = null)
     * @method Grid\Column|Collection transfer_agent5_rate(string $label = null)
     * @method Grid\Column|Collection transfer_balance_amount(string $label = null)
     * @method Grid\Column|Collection transfer_notice(string $label = null)
     * @method Grid\Column|Collection transfer_user_rate(string $label = null)
     * @method Grid\Column|Collection user_group_id(string $label = null)
     * @method Grid\Column|Collection zeros_balance(string $label = null)
     * @method Grid\Column|Collection api_messages_count(string $label = null)
     * @method Grid\Column|Collection app_id(string $label = null)
     * @method Grid\Column|Collection peak_connections_count(string $label = null)
     * @method Grid\Column|Collection websocket_messages_count(string $label = null)
     */
    class Grid {}

    class MiniGrid extends Grid {}

    /**
     * @property Show\Field|Collection batch_uuid
     * @property Show\Field|Collection causer_id
     * @property Show\Field|Collection causer_type
     * @property Show\Field|Collection created_at
     * @property Show\Field|Collection event
     * @property Show\Field|Collection id
     * @property Show\Field|Collection log_name
     * @property Show\Field|Collection properties
     * @property Show\Field|Collection subject_id
     * @property Show\Field|Collection subject_type
     * @property Show\Field|Collection updated_at
     * @property Show\Field|Collection detail
     * @property Show\Field|Collection name
     * @property Show\Field|Collection type
     * @property Show\Field|Collection version
     * @property Show\Field|Collection is_enabled
     * @property Show\Field|Collection extension
     * @property Show\Field|Collection icon
     * @property Show\Field|Collection order
     * @property Show\Field|Collection parent_id
     * @property Show\Field|Collection uri
     * @property Show\Field|Collection app_type
     * @property Show\Field|Collection input
     * @property Show\Field|Collection ip
     * @property Show\Field|Collection method
     * @property Show\Field|Collection path
     * @property Show\Field|Collection target_type
     * @property Show\Field|Collection user_id
     * @property Show\Field|Collection menu_id
     * @property Show\Field|Collection permission_id
     * @property Show\Field|Collection http_method
     * @property Show\Field|Collection http_path
     * @property Show\Field|Collection slug
     * @property Show\Field|Collection role_id
     * @property Show\Field|Collection value
     * @property Show\Field|Collection avatar
     * @property Show\Field|Collection google_two_fa_bind
     * @property Show\Field|Collection google_two_fa_enable
     * @property Show\Field|Collection google_two_fa_secret
     * @property Show\Field|Collection last_login_ip
     * @property Show\Field|Collection last_login_time
     * @property Show\Field|Collection login_white_ip
     * @property Show\Field|Collection password
     * @property Show\Field|Collection remember_token
     * @property Show\Field|Collection session_id
     * @property Show\Field|Collection status
     * @property Show\Field|Collection username
     * @property Show\Field|Collection action_agent_id
     * @property Show\Field|Collection agent_id
     * @property Show\Field|Collection amount
     * @property Show\Field|Collection balance_amount
     * @property Show\Field|Collection deleted_at
     * @property Show\Field|Collection mid
     * @property Show\Field|Collection remark
     * @property Show\Field|Collection type_id
     * @property Show\Field|Collection child_id
     * @property Show\Field|Collection level
     * @property Show\Field|Collection pid
     * @property Show\Field|Collection code
     * @property Show\Field|Collection currency_id
     * @property Show\Field|Collection telegram_group_id
     * @property Show\Field|Collection chu_total_amount
     * @property Show\Field|Collection rate
     * @property Show\Field|Collection rate1
     * @property Show\Field|Collection ru_total_amount
     * @property Show\Field|Collection content
     * @property Show\Field|Collection channel_id
     * @property Show\Field|Collection collection_max_amount
     * @property Show\Field|Collection collection_min_amount
     * @property Show\Field|Collection collection_total_amount
     * @property Show\Field|Collection debug_logs
     * @property Show\Field|Collection params
     * @property Show\Field|Collection pay_max_amount
     * @property Show\Field|Collection pay_min_amount
     * @property Show\Field|Collection pay_total_amount
     * @property Show\Field|Collection public_params
     * @property Show\Field|Collection secret_params
     * @property Show\Field|Collection bank_code_id
     * @property Show\Field|Collection fixed_rate
     * @property Show\Field|Collection payment_id
     * @property Show\Field|Collection auto_priority
     * @property Show\Field|Collection auto_query_status
     * @property Show\Field|Collection balance_update_time
     * @property Show\Field|Collection batch_transfer
     * @property Show\Field|Collection callback_white_ip
     * @property Show\Field|Collection cashier_payment
     * @property Show\Field|Collection classname
     * @property Show\Field|Collection coder
     * @property Show\Field|Collection currency
     * @property Show\Field|Collection deposit_order_query
     * @property Show\Field|Collection is_cashier_on
     * @property Show\Field|Collection is_json_return
     * @property Show\Field|Collection is_real_name
     * @property Show\Field|Collection payment_ids
     * @property Show\Field|Collection priority
     * @property Show\Field|Collection telegram_user_id
     * @property Show\Field|Collection transfer_order_query
     * @property Show\Field|Collection transfer_payment
     * @property Show\Field|Collection use_cashier
     * @property Show\Field|Collection date_add
     * @property Show\Field|Collection status1_count
     * @property Show\Field|Collection status2_count
     * @property Show\Field|Collection status3_count
     * @property Show\Field|Collection status4_count
     * @property Show\Field|Collection status5_count
     * @property Show\Field|Collection status6_count
     * @property Show\Field|Collection total_amount
     * @property Show\Field|Collection total_count
     * @property Show\Field|Collection account_type
     * @property Show\Field|Collection actual_amount
     * @property Show\Field|Collection alipay_uid
     * @property Show\Field|Collection bank
     * @property Show\Field|Collection bank_code
     * @property Show\Field|Collection bank_id
     * @property Show\Field|Collection bank_name
     * @property Show\Field|Collection callback_count
     * @property Show\Field|Collection callback_status
     * @property Show\Field|Collection callback_time
     * @property Show\Field|Collection card_name
     * @property Show\Field|Collection card_no
     * @property Show\Field|Collection channel_account_id
     * @property Show\Field|Collection channel_cost
     * @property Show\Field|Collection channel_info
     * @property Show\Field|Collection channel_ordernumber
     * @property Show\Field|Collection channel_pay_url
     * @property Show\Field|Collection channel_rate
     * @property Show\Field|Collection collection_app_info
     * @property Show\Field|Collection collection_app_link
     * @property Show\Field|Collection collection_bank_branch
     * @property Show\Field|Collection collection_bank_code
     * @property Show\Field|Collection collection_bank_name
     * @property Show\Field|Collection collection_card_no
     * @property Show\Field|Collection collection_name
     * @property Show\Field|Collection collection_qrcode
     * @property Show\Field|Collection collection_qrcode_url
     * @property Show\Field|Collection confirm_time
     * @property Show\Field|Collection data_type
     * @property Show\Field|Collection email
     * @property Show\Field|Collection expired_time
     * @property Show\Field|Collection extra
     * @property Show\Field|Collection fee
     * @property Show\Field|Collection freeze_amount
     * @property Show\Field|Collection hand_admin_id
     * @property Show\Field|Collection hand_success
     * @property Show\Field|Collection hour
     * @property Show\Field|Collection merchant_agent1_commission
     * @property Show\Field|Collection merchant_agent1_id
     * @property Show\Field|Collection merchant_agent1_rate
     * @property Show\Field|Collection merchant_agent2_commission
     * @property Show\Field|Collection merchant_agent2_id
     * @property Show\Field|Collection merchant_agent2_rate
     * @property Show\Field|Collection merchant_agent3_commission
     * @property Show\Field|Collection merchant_agent3_id
     * @property Show\Field|Collection merchant_agent3_rate
     * @property Show\Field|Collection merchant_extra_fee
     * @property Show\Field|Collection merchant_fee
     * @property Show\Field|Collection merchant_rate
     * @property Show\Field|Collection notify_url
     * @property Show\Field|Collection order_no
     * @property Show\Field|Collection order_type
     * @property Show\Field|Collection ordernumber
     * @property Show\Field|Collection pay_amount
     * @property Show\Field|Collection pay_certificate
     * @property Show\Field|Collection pay_name
     * @property Show\Field|Collection pay_status
     * @property Show\Field|Collection phone
     * @property Show\Field|Collection profit
     * @property Show\Field|Collection query_message_content
     * @property Show\Field|Collection return_url
     * @property Show\Field|Collection settlement_mode
     * @property Show\Field|Collection settlement_time
     * @property Show\Field|Collection show_amount
     * @property Show\Field|Collection success_time
     * @property Show\Field|Collection tag
     * @property Show\Field|Collection time
     * @property Show\Field|Collection true_ip
     * @property Show\Field|Collection uid
     * @property Show\Field|Collection usdt_rate
     * @property Show\Field|Collection user_agent1_commission
     * @property Show\Field|Collection user_agent1_id
     * @property Show\Field|Collection user_agent1_rate
     * @property Show\Field|Collection user_agent2_commission
     * @property Show\Field|Collection user_agent2_id
     * @property Show\Field|Collection user_agent2_rate
     * @property Show\Field|Collection user_agent3_commission
     * @property Show\Field|Collection user_agent3_id
     * @property Show\Field|Collection user_agent3_rate
     * @property Show\Field|Collection user_agent4_commission
     * @property Show\Field|Collection user_agent4_id
     * @property Show\Field|Collection user_agent4_rate
     * @property Show\Field|Collection user_agent5_commission
     * @property Show\Field|Collection user_agent5_id
     * @property Show\Field|Collection user_agent5_rate
     * @property Show\Field|Collection user_bank_id
     * @property Show\Field|Collection user_commission
     * @property Show\Field|Collection user_rate
     * @property Show\Field|Collection utr
     * @property Show\Field|Collection message
     * @property Show\Field|Collection order_id
     * @property Show\Field|Collection connection
     * @property Show\Field|Collection exception
     * @property Show\Field|Collection failed_at
     * @property Show\Field|Collection payload
     * @property Show\Field|Collection queue
     * @property Show\Field|Collection uuid
     * @property Show\Field|Collection deposit_order_id
     * @property Show\Field|Collection unfreeze_time
     * @property Show\Field|Collection address
     * @property Show\Field|Collection chat_id
     * @property Show\Field|Collection count
     * @property Show\Field|Collection attempts
     * @property Show\Field|Collection available_at
     * @property Show\Field|Collection reserved_at
     * @property Show\Field|Collection trx_balance
     * @property Show\Field|Collection usdt_balance
     * @property Show\Field|Collection deposit_total_amount
     * @property Show\Field|Collection deposit_total_income
     * @property Show\Field|Collection total_income
     * @property Show\Field|Collection transfer_total_amount
     * @property Show\Field|Collection transfer_total_income
     * @property Show\Field|Collection account_cny
     * @property Show\Field|Collection account_usdt
     * @property Show\Field|Collection account_usdt_rate
     * @property Show\Field|Collection order_cny
     * @property Show\Field|Collection order_usdt
     * @property Show\Field|Collection order_usdt_rate
     * @property Show\Field|Collection total_cny
     * @property Show\Field|Collection total_usdt
     * @property Show\Field|Collection usdt_avg_rate
     * @property Show\Field|Collection admin_id
     * @property Show\Field|Collection settlement_amount
     * @property Show\Field|Collection deposit_fee
     * @property Show\Field|Collection float_status
     * @property Show\Field|Collection merchant_user_id
     * @property Show\Field|Collection agent_user_id
     * @property Show\Field|Collection amount_float_type
     * @property Show\Field|Collection appkey
     * @property Show\Field|Collection appsecret
     * @property Show\Field|Collection available_balance
     * @property Show\Field|Collection cashier_domain
     * @property Show\Field|Collection check_order
     * @property Show\Field|Collection default_usdt_ava_rate
     * @property Show\Field|Collection deposit_channel_mode
     * @property Show\Field|Collection deposits_callback_url
     * @property Show\Field|Collection float_amount
     * @property Show\Field|Collection history_balance_amount
     * @property Show\Field|Collection history_end_balance_amount_time
     * @property Show\Field|Collection is_need_decimal
     * @property Show\Field|Collection is_usdt_ava_rate
     * @property Show\Field|Collection last_balance_amount_time
     * @property Show\Field|Collection manager_telegram_user_id
     * @property Show\Field|Collection pay_white_ip
     * @property Show\Field|Collection sign_space
     * @property Show\Field|Collection transfer_callback_url
     * @property Show\Field|Collection transfer_channel_mode
     * @property Show\Field|Collection usdt_ava_rate
     * @property Show\Field|Collection usdt_float_rate
     * @property Show\Field|Collection withdraw_white_ip
     * @property Show\Field|Collection agent1_rate
     * @property Show\Field|Collection agent2_rate
     * @property Show\Field|Collection agent3_rate
     * @property Show\Field|Collection max_limit_amount
     * @property Show\Field|Collection min_limit_amount
     * @property Show\Field|Collection pay_rate
     * @property Show\Field|Collection transfer_rates
     * @property Show\Field|Collection action_admin_id
     * @property Show\Field|Collection amount_password
     * @property Show\Field|Collection abilities
     * @property Show\Field|Collection expires_at
     * @property Show\Field|Collection last_used_at
     * @property Show\Field|Collection token
     * @property Show\Field|Collection tokenable_id
     * @property Show\Field|Collection tokenable_type
     * @property Show\Field|Collection cid
     * @property Show\Field|Collection deposit_order_number_fail
     * @property Show\Field|Collection deposit_order_number_overtime
     * @property Show\Field|Collection deposit_order_number_success
     * @property Show\Field|Collection deposit_order_number_swiping
     * @property Show\Field|Collection deposit_order_number_total
     * @property Show\Field|Collection deposit_order_total_amount
     * @property Show\Field|Collection deposit_order_total_fee
     * @property Show\Field|Collection deposit_profit
     * @property Show\Field|Collection settlement_order_number_fail
     * @property Show\Field|Collection settlement_order_number_success
     * @property Show\Field|Collection settlement_order_number_total
     * @property Show\Field|Collection settlement_order_total_amount
     * @property Show\Field|Collection settlement_order_total_fee
     * @property Show\Field|Collection settlement_profit
     * @property Show\Field|Collection transfer_order_number_fail
     * @property Show\Field|Collection transfer_order_number_success
     * @property Show\Field|Collection transfer_order_number_total
     * @property Show\Field|Collection transfer_order_total_amount
     * @property Show\Field|Collection transfer_order_total_fee
     * @property Show\Field|Collection transfer_profit
     * @property Show\Field|Collection add_total_amount
     * @property Show\Field|Collection aid
     * @property Show\Field|Collection deposit_commission
     * @property Show\Field|Collection jian_total_amount
     * @property Show\Field|Collection settlement_commission
     * @property Show\Field|Collection transfer_commission
     * @property Show\Field|Collection deposit_one_agent_commission
     * @property Show\Field|Collection deposit_three_agent_commission
     * @property Show\Field|Collection deposit_two_agent_commission
     * @property Show\Field|Collection settlement_one_agent_commission
     * @property Show\Field|Collection settlement_three_agent_commission
     * @property Show\Field|Collection settlement_two_agent_commission
     * @property Show\Field|Collection transfer_one_agent_commission
     * @property Show\Field|Collection transfer_three_agent_commission
     * @property Show\Field|Collection transfer_two_agent_commission
     * @property Show\Field|Collection ubid
     * @property Show\Field|Collection commission_add_total_amount
     * @property Show\Field|Collection commission_jian_total_amount
     * @property Show\Field|Collection deposit_add_total_amount
     * @property Show\Field|Collection deposit_five_agent_commission
     * @property Show\Field|Collection deposit_four_agent_commission
     * @property Show\Field|Collection deposit_jian_total_amount
     * @property Show\Field|Collection settlement_five_agent_commission
     * @property Show\Field|Collection settlement_four_agent_commission
     * @property Show\Field|Collection transfer_add_total_amount
     * @property Show\Field|Collection transfer_five_agent_commission
     * @property Show\Field|Collection transfer_four_agent_commission
     * @property Show\Field|Collection transfer_jian_total_amount
     * @property Show\Field|Collection data_content
     * @property Show\Field|Collection admin_action_id
     * @property Show\Field|Collection bank_branch
     * @property Show\Field|Collection bank_city
     * @property Show\Field|Collection bank_mobile
     * @property Show\Field|Collection bank_province
     * @property Show\Field|Collection callToken
     * @property Show\Field|Collection child_count
     * @property Show\Field|Collection holder_name
     * @property Show\Field|Collection identity_no
     * @property Show\Field|Collection merchant_action_id
     * @property Show\Field|Collection pay_certificate_1
     * @property Show\Field|Collection pay_certificate_2
     * @property Show\Field|Collection pay_certificate_3
     * @property Show\Field|Collection resetpay_number
     * @property Show\Field|Collection withdrawQueryUrl
     * @property Show\Field|Collection action_user_id
     * @property Show\Field|Collection is_agent
     * @property Show\Field|Collection type_balance_amount
     * @property Show\Field|Collection action
     * @property Show\Field|Collection collection_status
     * @property Show\Field|Collection doing_status
     * @property Show\Field|Collection is_mobile_bank
     * @property Show\Field|Collection limint_day_amount
     * @property Show\Field|Collection limint_max_amount
     * @property Show\Field|Collection limint_min_amount
     * @property Show\Field|Collection limit_day_order_number
     * @property Show\Field|Collection merchant_user_ids
     * @property Show\Field|Collection payment_qrcode
     * @property Show\Field|Collection payment_qrcode_url
     * @property Show\Field|Collection same_amount_interval_time
     * @property Show\Field|Collection extra_user_ids
     * @property Show\Field|Collection specialized_merchant_user_ids
     * @property Show\Field|Collection commission
     * @property Show\Field|Collection account_types
     * @property Show\Field|Collection acquisition_status
     * @property Show\Field|Collection action_amount
     * @property Show\Field|Collection action_collection_status
     * @property Show\Field|Collection action_delete
     * @property Show\Field|Collection action_limit_card
     * @property Show\Field|Collection action_method
     * @property Show\Field|Collection admin_user_id
     * @property Show\Field|Collection agent4_rate
     * @property Show\Field|Collection agent5_rate
     * @property Show\Field|Collection auto_refresh
     * @property Show\Field|Collection collection_group_merchant_ids
     * @property Show\Field|Collection collection_limit_max
     * @property Show\Field|Collection collection_limit_min
     * @property Show\Field|Collection commission_balance_amount
     * @property Show\Field|Collection deposit_agent1_rate
     * @property Show\Field|Collection deposit_agent2_rate
     * @property Show\Field|Collection deposit_agent3_rate
     * @property Show\Field|Collection deposit_agent4_rate
     * @property Show\Field|Collection deposit_agent5_rate
     * @property Show\Field|Collection deposit_amount
     * @property Show\Field|Collection deposit_balance_amount
     * @property Show\Field|Collection deposit_notice
     * @property Show\Field|Collection deposit_user_rate
     * @property Show\Field|Collection limit_deposit_paid_number
     * @property Show\Field|Collection lock_user
     * @property Show\Field|Collection mobile
     * @property Show\Field|Collection pay_group_merchant_user_ids
     * @property Show\Field|Collection pay_limit_max
     * @property Show\Field|Collection pay_limit_min
     * @property Show\Field|Collection self_add_bank
     * @property Show\Field|Collection settlement_agent1_rate
     * @property Show\Field|Collection settlement_agent2_rate
     * @property Show\Field|Collection settlement_agent3_rate
     * @property Show\Field|Collection settlement_agent4_rate
     * @property Show\Field|Collection settlement_agent5_rate
     * @property Show\Field|Collection settlement_user_rate
     * @property Show\Field|Collection transfer_agent1_rate
     * @property Show\Field|Collection transfer_agent2_rate
     * @property Show\Field|Collection transfer_agent3_rate
     * @property Show\Field|Collection transfer_agent4_rate
     * @property Show\Field|Collection transfer_agent5_rate
     * @property Show\Field|Collection transfer_balance_amount
     * @property Show\Field|Collection transfer_notice
     * @property Show\Field|Collection transfer_user_rate
     * @property Show\Field|Collection user_group_id
     * @property Show\Field|Collection zeros_balance
     * @property Show\Field|Collection api_messages_count
     * @property Show\Field|Collection app_id
     * @property Show\Field|Collection peak_connections_count
     * @property Show\Field|Collection websocket_messages_count
     *
     * @method Show\Field|Collection batch_uuid(string $label = null)
     * @method Show\Field|Collection causer_id(string $label = null)
     * @method Show\Field|Collection causer_type(string $label = null)
     * @method Show\Field|Collection created_at(string $label = null)
     * @method Show\Field|Collection event(string $label = null)
     * @method Show\Field|Collection id(string $label = null)
     * @method Show\Field|Collection log_name(string $label = null)
     * @method Show\Field|Collection properties(string $label = null)
     * @method Show\Field|Collection subject_id(string $label = null)
     * @method Show\Field|Collection subject_type(string $label = null)
     * @method Show\Field|Collection updated_at(string $label = null)
     * @method Show\Field|Collection detail(string $label = null)
     * @method Show\Field|Collection name(string $label = null)
     * @method Show\Field|Collection type(string $label = null)
     * @method Show\Field|Collection version(string $label = null)
     * @method Show\Field|Collection is_enabled(string $label = null)
     * @method Show\Field|Collection extension(string $label = null)
     * @method Show\Field|Collection icon(string $label = null)
     * @method Show\Field|Collection order(string $label = null)
     * @method Show\Field|Collection parent_id(string $label = null)
     * @method Show\Field|Collection uri(string $label = null)
     * @method Show\Field|Collection app_type(string $label = null)
     * @method Show\Field|Collection input(string $label = null)
     * @method Show\Field|Collection ip(string $label = null)
     * @method Show\Field|Collection method(string $label = null)
     * @method Show\Field|Collection path(string $label = null)
     * @method Show\Field|Collection target_type(string $label = null)
     * @method Show\Field|Collection user_id(string $label = null)
     * @method Show\Field|Collection menu_id(string $label = null)
     * @method Show\Field|Collection permission_id(string $label = null)
     * @method Show\Field|Collection http_method(string $label = null)
     * @method Show\Field|Collection http_path(string $label = null)
     * @method Show\Field|Collection slug(string $label = null)
     * @method Show\Field|Collection role_id(string $label = null)
     * @method Show\Field|Collection value(string $label = null)
     * @method Show\Field|Collection avatar(string $label = null)
     * @method Show\Field|Collection google_two_fa_bind(string $label = null)
     * @method Show\Field|Collection google_two_fa_enable(string $label = null)
     * @method Show\Field|Collection google_two_fa_secret(string $label = null)
     * @method Show\Field|Collection last_login_ip(string $label = null)
     * @method Show\Field|Collection last_login_time(string $label = null)
     * @method Show\Field|Collection login_white_ip(string $label = null)
     * @method Show\Field|Collection password(string $label = null)
     * @method Show\Field|Collection remember_token(string $label = null)
     * @method Show\Field|Collection session_id(string $label = null)
     * @method Show\Field|Collection status(string $label = null)
     * @method Show\Field|Collection username(string $label = null)
     * @method Show\Field|Collection action_agent_id(string $label = null)
     * @method Show\Field|Collection agent_id(string $label = null)
     * @method Show\Field|Collection amount(string $label = null)
     * @method Show\Field|Collection balance_amount(string $label = null)
     * @method Show\Field|Collection deleted_at(string $label = null)
     * @method Show\Field|Collection mid(string $label = null)
     * @method Show\Field|Collection remark(string $label = null)
     * @method Show\Field|Collection type_id(string $label = null)
     * @method Show\Field|Collection child_id(string $label = null)
     * @method Show\Field|Collection level(string $label = null)
     * @method Show\Field|Collection pid(string $label = null)
     * @method Show\Field|Collection code(string $label = null)
     * @method Show\Field|Collection currency_id(string $label = null)
     * @method Show\Field|Collection telegram_group_id(string $label = null)
     * @method Show\Field|Collection chu_total_amount(string $label = null)
     * @method Show\Field|Collection rate(string $label = null)
     * @method Show\Field|Collection rate1(string $label = null)
     * @method Show\Field|Collection ru_total_amount(string $label = null)
     * @method Show\Field|Collection content(string $label = null)
     * @method Show\Field|Collection channel_id(string $label = null)
     * @method Show\Field|Collection collection_max_amount(string $label = null)
     * @method Show\Field|Collection collection_min_amount(string $label = null)
     * @method Show\Field|Collection collection_total_amount(string $label = null)
     * @method Show\Field|Collection debug_logs(string $label = null)
     * @method Show\Field|Collection params(string $label = null)
     * @method Show\Field|Collection pay_max_amount(string $label = null)
     * @method Show\Field|Collection pay_min_amount(string $label = null)
     * @method Show\Field|Collection pay_total_amount(string $label = null)
     * @method Show\Field|Collection public_params(string $label = null)
     * @method Show\Field|Collection secret_params(string $label = null)
     * @method Show\Field|Collection bank_code_id(string $label = null)
     * @method Show\Field|Collection fixed_rate(string $label = null)
     * @method Show\Field|Collection payment_id(string $label = null)
     * @method Show\Field|Collection auto_priority(string $label = null)
     * @method Show\Field|Collection auto_query_status(string $label = null)
     * @method Show\Field|Collection balance_update_time(string $label = null)
     * @method Show\Field|Collection batch_transfer(string $label = null)
     * @method Show\Field|Collection callback_white_ip(string $label = null)
     * @method Show\Field|Collection cashier_payment(string $label = null)
     * @method Show\Field|Collection classname(string $label = null)
     * @method Show\Field|Collection coder(string $label = null)
     * @method Show\Field|Collection currency(string $label = null)
     * @method Show\Field|Collection deposit_order_query(string $label = null)
     * @method Show\Field|Collection is_cashier_on(string $label = null)
     * @method Show\Field|Collection is_json_return(string $label = null)
     * @method Show\Field|Collection is_real_name(string $label = null)
     * @method Show\Field|Collection payment_ids(string $label = null)
     * @method Show\Field|Collection priority(string $label = null)
     * @method Show\Field|Collection telegram_user_id(string $label = null)
     * @method Show\Field|Collection transfer_order_query(string $label = null)
     * @method Show\Field|Collection transfer_payment(string $label = null)
     * @method Show\Field|Collection use_cashier(string $label = null)
     * @method Show\Field|Collection date_add(string $label = null)
     * @method Show\Field|Collection status1_count(string $label = null)
     * @method Show\Field|Collection status2_count(string $label = null)
     * @method Show\Field|Collection status3_count(string $label = null)
     * @method Show\Field|Collection status4_count(string $label = null)
     * @method Show\Field|Collection status5_count(string $label = null)
     * @method Show\Field|Collection status6_count(string $label = null)
     * @method Show\Field|Collection total_amount(string $label = null)
     * @method Show\Field|Collection total_count(string $label = null)
     * @method Show\Field|Collection account_type(string $label = null)
     * @method Show\Field|Collection actual_amount(string $label = null)
     * @method Show\Field|Collection alipay_uid(string $label = null)
     * @method Show\Field|Collection bank(string $label = null)
     * @method Show\Field|Collection bank_code(string $label = null)
     * @method Show\Field|Collection bank_id(string $label = null)
     * @method Show\Field|Collection bank_name(string $label = null)
     * @method Show\Field|Collection callback_count(string $label = null)
     * @method Show\Field|Collection callback_status(string $label = null)
     * @method Show\Field|Collection callback_time(string $label = null)
     * @method Show\Field|Collection card_name(string $label = null)
     * @method Show\Field|Collection card_no(string $label = null)
     * @method Show\Field|Collection channel_account_id(string $label = null)
     * @method Show\Field|Collection channel_cost(string $label = null)
     * @method Show\Field|Collection channel_info(string $label = null)
     * @method Show\Field|Collection channel_ordernumber(string $label = null)
     * @method Show\Field|Collection channel_pay_url(string $label = null)
     * @method Show\Field|Collection channel_rate(string $label = null)
     * @method Show\Field|Collection collection_app_info(string $label = null)
     * @method Show\Field|Collection collection_app_link(string $label = null)
     * @method Show\Field|Collection collection_bank_branch(string $label = null)
     * @method Show\Field|Collection collection_bank_code(string $label = null)
     * @method Show\Field|Collection collection_bank_name(string $label = null)
     * @method Show\Field|Collection collection_card_no(string $label = null)
     * @method Show\Field|Collection collection_name(string $label = null)
     * @method Show\Field|Collection collection_qrcode(string $label = null)
     * @method Show\Field|Collection collection_qrcode_url(string $label = null)
     * @method Show\Field|Collection confirm_time(string $label = null)
     * @method Show\Field|Collection data_type(string $label = null)
     * @method Show\Field|Collection email(string $label = null)
     * @method Show\Field|Collection expired_time(string $label = null)
     * @method Show\Field|Collection extra(string $label = null)
     * @method Show\Field|Collection fee(string $label = null)
     * @method Show\Field|Collection freeze_amount(string $label = null)
     * @method Show\Field|Collection hand_admin_id(string $label = null)
     * @method Show\Field|Collection hand_success(string $label = null)
     * @method Show\Field|Collection hour(string $label = null)
     * @method Show\Field|Collection merchant_agent1_commission(string $label = null)
     * @method Show\Field|Collection merchant_agent1_id(string $label = null)
     * @method Show\Field|Collection merchant_agent1_rate(string $label = null)
     * @method Show\Field|Collection merchant_agent2_commission(string $label = null)
     * @method Show\Field|Collection merchant_agent2_id(string $label = null)
     * @method Show\Field|Collection merchant_agent2_rate(string $label = null)
     * @method Show\Field|Collection merchant_agent3_commission(string $label = null)
     * @method Show\Field|Collection merchant_agent3_id(string $label = null)
     * @method Show\Field|Collection merchant_agent3_rate(string $label = null)
     * @method Show\Field|Collection merchant_extra_fee(string $label = null)
     * @method Show\Field|Collection merchant_fee(string $label = null)
     * @method Show\Field|Collection merchant_rate(string $label = null)
     * @method Show\Field|Collection notify_url(string $label = null)
     * @method Show\Field|Collection order_no(string $label = null)
     * @method Show\Field|Collection order_type(string $label = null)
     * @method Show\Field|Collection ordernumber(string $label = null)
     * @method Show\Field|Collection pay_amount(string $label = null)
     * @method Show\Field|Collection pay_certificate(string $label = null)
     * @method Show\Field|Collection pay_name(string $label = null)
     * @method Show\Field|Collection pay_status(string $label = null)
     * @method Show\Field|Collection phone(string $label = null)
     * @method Show\Field|Collection profit(string $label = null)
     * @method Show\Field|Collection query_message_content(string $label = null)
     * @method Show\Field|Collection return_url(string $label = null)
     * @method Show\Field|Collection settlement_mode(string $label = null)
     * @method Show\Field|Collection settlement_time(string $label = null)
     * @method Show\Field|Collection show_amount(string $label = null)
     * @method Show\Field|Collection success_time(string $label = null)
     * @method Show\Field|Collection tag(string $label = null)
     * @method Show\Field|Collection time(string $label = null)
     * @method Show\Field|Collection true_ip(string $label = null)
     * @method Show\Field|Collection uid(string $label = null)
     * @method Show\Field|Collection usdt_rate(string $label = null)
     * @method Show\Field|Collection user_agent1_commission(string $label = null)
     * @method Show\Field|Collection user_agent1_id(string $label = null)
     * @method Show\Field|Collection user_agent1_rate(string $label = null)
     * @method Show\Field|Collection user_agent2_commission(string $label = null)
     * @method Show\Field|Collection user_agent2_id(string $label = null)
     * @method Show\Field|Collection user_agent2_rate(string $label = null)
     * @method Show\Field|Collection user_agent3_commission(string $label = null)
     * @method Show\Field|Collection user_agent3_id(string $label = null)
     * @method Show\Field|Collection user_agent3_rate(string $label = null)
     * @method Show\Field|Collection user_agent4_commission(string $label = null)
     * @method Show\Field|Collection user_agent4_id(string $label = null)
     * @method Show\Field|Collection user_agent4_rate(string $label = null)
     * @method Show\Field|Collection user_agent5_commission(string $label = null)
     * @method Show\Field|Collection user_agent5_id(string $label = null)
     * @method Show\Field|Collection user_agent5_rate(string $label = null)
     * @method Show\Field|Collection user_bank_id(string $label = null)
     * @method Show\Field|Collection user_commission(string $label = null)
     * @method Show\Field|Collection user_rate(string $label = null)
     * @method Show\Field|Collection utr(string $label = null)
     * @method Show\Field|Collection message(string $label = null)
     * @method Show\Field|Collection order_id(string $label = null)
     * @method Show\Field|Collection connection(string $label = null)
     * @method Show\Field|Collection exception(string $label = null)
     * @method Show\Field|Collection failed_at(string $label = null)
     * @method Show\Field|Collection payload(string $label = null)
     * @method Show\Field|Collection queue(string $label = null)
     * @method Show\Field|Collection uuid(string $label = null)
     * @method Show\Field|Collection deposit_order_id(string $label = null)
     * @method Show\Field|Collection unfreeze_time(string $label = null)
     * @method Show\Field|Collection address(string $label = null)
     * @method Show\Field|Collection chat_id(string $label = null)
     * @method Show\Field|Collection count(string $label = null)
     * @method Show\Field|Collection attempts(string $label = null)
     * @method Show\Field|Collection available_at(string $label = null)
     * @method Show\Field|Collection reserved_at(string $label = null)
     * @method Show\Field|Collection trx_balance(string $label = null)
     * @method Show\Field|Collection usdt_balance(string $label = null)
     * @method Show\Field|Collection deposit_total_amount(string $label = null)
     * @method Show\Field|Collection deposit_total_income(string $label = null)
     * @method Show\Field|Collection total_income(string $label = null)
     * @method Show\Field|Collection transfer_total_amount(string $label = null)
     * @method Show\Field|Collection transfer_total_income(string $label = null)
     * @method Show\Field|Collection account_cny(string $label = null)
     * @method Show\Field|Collection account_usdt(string $label = null)
     * @method Show\Field|Collection account_usdt_rate(string $label = null)
     * @method Show\Field|Collection order_cny(string $label = null)
     * @method Show\Field|Collection order_usdt(string $label = null)
     * @method Show\Field|Collection order_usdt_rate(string $label = null)
     * @method Show\Field|Collection total_cny(string $label = null)
     * @method Show\Field|Collection total_usdt(string $label = null)
     * @method Show\Field|Collection usdt_avg_rate(string $label = null)
     * @method Show\Field|Collection admin_id(string $label = null)
     * @method Show\Field|Collection settlement_amount(string $label = null)
     * @method Show\Field|Collection deposit_fee(string $label = null)
     * @method Show\Field|Collection float_status(string $label = null)
     * @method Show\Field|Collection merchant_user_id(string $label = null)
     * @method Show\Field|Collection agent_user_id(string $label = null)
     * @method Show\Field|Collection amount_float_type(string $label = null)
     * @method Show\Field|Collection appkey(string $label = null)
     * @method Show\Field|Collection appsecret(string $label = null)
     * @method Show\Field|Collection available_balance(string $label = null)
     * @method Show\Field|Collection cashier_domain(string $label = null)
     * @method Show\Field|Collection check_order(string $label = null)
     * @method Show\Field|Collection default_usdt_ava_rate(string $label = null)
     * @method Show\Field|Collection deposit_channel_mode(string $label = null)
     * @method Show\Field|Collection deposits_callback_url(string $label = null)
     * @method Show\Field|Collection float_amount(string $label = null)
     * @method Show\Field|Collection history_balance_amount(string $label = null)
     * @method Show\Field|Collection history_end_balance_amount_time(string $label = null)
     * @method Show\Field|Collection is_need_decimal(string $label = null)
     * @method Show\Field|Collection is_usdt_ava_rate(string $label = null)
     * @method Show\Field|Collection last_balance_amount_time(string $label = null)
     * @method Show\Field|Collection manager_telegram_user_id(string $label = null)
     * @method Show\Field|Collection pay_white_ip(string $label = null)
     * @method Show\Field|Collection sign_space(string $label = null)
     * @method Show\Field|Collection transfer_callback_url(string $label = null)
     * @method Show\Field|Collection transfer_channel_mode(string $label = null)
     * @method Show\Field|Collection usdt_ava_rate(string $label = null)
     * @method Show\Field|Collection usdt_float_rate(string $label = null)
     * @method Show\Field|Collection withdraw_white_ip(string $label = null)
     * @method Show\Field|Collection agent1_rate(string $label = null)
     * @method Show\Field|Collection agent2_rate(string $label = null)
     * @method Show\Field|Collection agent3_rate(string $label = null)
     * @method Show\Field|Collection max_limit_amount(string $label = null)
     * @method Show\Field|Collection min_limit_amount(string $label = null)
     * @method Show\Field|Collection pay_rate(string $label = null)
     * @method Show\Field|Collection transfer_rates(string $label = null)
     * @method Show\Field|Collection action_admin_id(string $label = null)
     * @method Show\Field|Collection amount_password(string $label = null)
     * @method Show\Field|Collection abilities(string $label = null)
     * @method Show\Field|Collection expires_at(string $label = null)
     * @method Show\Field|Collection last_used_at(string $label = null)
     * @method Show\Field|Collection token(string $label = null)
     * @method Show\Field|Collection tokenable_id(string $label = null)
     * @method Show\Field|Collection tokenable_type(string $label = null)
     * @method Show\Field|Collection cid(string $label = null)
     * @method Show\Field|Collection deposit_order_number_fail(string $label = null)
     * @method Show\Field|Collection deposit_order_number_overtime(string $label = null)
     * @method Show\Field|Collection deposit_order_number_success(string $label = null)
     * @method Show\Field|Collection deposit_order_number_swiping(string $label = null)
     * @method Show\Field|Collection deposit_order_number_total(string $label = null)
     * @method Show\Field|Collection deposit_order_total_amount(string $label = null)
     * @method Show\Field|Collection deposit_order_total_fee(string $label = null)
     * @method Show\Field|Collection deposit_profit(string $label = null)
     * @method Show\Field|Collection settlement_order_number_fail(string $label = null)
     * @method Show\Field|Collection settlement_order_number_success(string $label = null)
     * @method Show\Field|Collection settlement_order_number_total(string $label = null)
     * @method Show\Field|Collection settlement_order_total_amount(string $label = null)
     * @method Show\Field|Collection settlement_order_total_fee(string $label = null)
     * @method Show\Field|Collection settlement_profit(string $label = null)
     * @method Show\Field|Collection transfer_order_number_fail(string $label = null)
     * @method Show\Field|Collection transfer_order_number_success(string $label = null)
     * @method Show\Field|Collection transfer_order_number_total(string $label = null)
     * @method Show\Field|Collection transfer_order_total_amount(string $label = null)
     * @method Show\Field|Collection transfer_order_total_fee(string $label = null)
     * @method Show\Field|Collection transfer_profit(string $label = null)
     * @method Show\Field|Collection add_total_amount(string $label = null)
     * @method Show\Field|Collection aid(string $label = null)
     * @method Show\Field|Collection deposit_commission(string $label = null)
     * @method Show\Field|Collection jian_total_amount(string $label = null)
     * @method Show\Field|Collection settlement_commission(string $label = null)
     * @method Show\Field|Collection transfer_commission(string $label = null)
     * @method Show\Field|Collection deposit_one_agent_commission(string $label = null)
     * @method Show\Field|Collection deposit_three_agent_commission(string $label = null)
     * @method Show\Field|Collection deposit_two_agent_commission(string $label = null)
     * @method Show\Field|Collection settlement_one_agent_commission(string $label = null)
     * @method Show\Field|Collection settlement_three_agent_commission(string $label = null)
     * @method Show\Field|Collection settlement_two_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_one_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_three_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_two_agent_commission(string $label = null)
     * @method Show\Field|Collection ubid(string $label = null)
     * @method Show\Field|Collection commission_add_total_amount(string $label = null)
     * @method Show\Field|Collection commission_jian_total_amount(string $label = null)
     * @method Show\Field|Collection deposit_add_total_amount(string $label = null)
     * @method Show\Field|Collection deposit_five_agent_commission(string $label = null)
     * @method Show\Field|Collection deposit_four_agent_commission(string $label = null)
     * @method Show\Field|Collection deposit_jian_total_amount(string $label = null)
     * @method Show\Field|Collection settlement_five_agent_commission(string $label = null)
     * @method Show\Field|Collection settlement_four_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_add_total_amount(string $label = null)
     * @method Show\Field|Collection transfer_five_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_four_agent_commission(string $label = null)
     * @method Show\Field|Collection transfer_jian_total_amount(string $label = null)
     * @method Show\Field|Collection data_content(string $label = null)
     * @method Show\Field|Collection admin_action_id(string $label = null)
     * @method Show\Field|Collection bank_branch(string $label = null)
     * @method Show\Field|Collection bank_city(string $label = null)
     * @method Show\Field|Collection bank_mobile(string $label = null)
     * @method Show\Field|Collection bank_province(string $label = null)
     * @method Show\Field|Collection callToken(string $label = null)
     * @method Show\Field|Collection child_count(string $label = null)
     * @method Show\Field|Collection holder_name(string $label = null)
     * @method Show\Field|Collection identity_no(string $label = null)
     * @method Show\Field|Collection merchant_action_id(string $label = null)
     * @method Show\Field|Collection pay_certificate_1(string $label = null)
     * @method Show\Field|Collection pay_certificate_2(string $label = null)
     * @method Show\Field|Collection pay_certificate_3(string $label = null)
     * @method Show\Field|Collection resetpay_number(string $label = null)
     * @method Show\Field|Collection withdrawQueryUrl(string $label = null)
     * @method Show\Field|Collection action_user_id(string $label = null)
     * @method Show\Field|Collection is_agent(string $label = null)
     * @method Show\Field|Collection type_balance_amount(string $label = null)
     * @method Show\Field|Collection action(string $label = null)
     * @method Show\Field|Collection collection_status(string $label = null)
     * @method Show\Field|Collection doing_status(string $label = null)
     * @method Show\Field|Collection is_mobile_bank(string $label = null)
     * @method Show\Field|Collection limint_day_amount(string $label = null)
     * @method Show\Field|Collection limint_max_amount(string $label = null)
     * @method Show\Field|Collection limint_min_amount(string $label = null)
     * @method Show\Field|Collection limit_day_order_number(string $label = null)
     * @method Show\Field|Collection merchant_user_ids(string $label = null)
     * @method Show\Field|Collection payment_qrcode(string $label = null)
     * @method Show\Field|Collection payment_qrcode_url(string $label = null)
     * @method Show\Field|Collection same_amount_interval_time(string $label = null)
     * @method Show\Field|Collection extra_user_ids(string $label = null)
     * @method Show\Field|Collection specialized_merchant_user_ids(string $label = null)
     * @method Show\Field|Collection commission(string $label = null)
     * @method Show\Field|Collection account_types(string $label = null)
     * @method Show\Field|Collection acquisition_status(string $label = null)
     * @method Show\Field|Collection action_amount(string $label = null)
     * @method Show\Field|Collection action_collection_status(string $label = null)
     * @method Show\Field|Collection action_delete(string $label = null)
     * @method Show\Field|Collection action_limit_card(string $label = null)
     * @method Show\Field|Collection action_method(string $label = null)
     * @method Show\Field|Collection admin_user_id(string $label = null)
     * @method Show\Field|Collection agent4_rate(string $label = null)
     * @method Show\Field|Collection agent5_rate(string $label = null)
     * @method Show\Field|Collection auto_refresh(string $label = null)
     * @method Show\Field|Collection collection_group_merchant_ids(string $label = null)
     * @method Show\Field|Collection collection_limit_max(string $label = null)
     * @method Show\Field|Collection collection_limit_min(string $label = null)
     * @method Show\Field|Collection commission_balance_amount(string $label = null)
     * @method Show\Field|Collection deposit_agent1_rate(string $label = null)
     * @method Show\Field|Collection deposit_agent2_rate(string $label = null)
     * @method Show\Field|Collection deposit_agent3_rate(string $label = null)
     * @method Show\Field|Collection deposit_agent4_rate(string $label = null)
     * @method Show\Field|Collection deposit_agent5_rate(string $label = null)
     * @method Show\Field|Collection deposit_amount(string $label = null)
     * @method Show\Field|Collection deposit_balance_amount(string $label = null)
     * @method Show\Field|Collection deposit_notice(string $label = null)
     * @method Show\Field|Collection deposit_user_rate(string $label = null)
     * @method Show\Field|Collection limit_deposit_paid_number(string $label = null)
     * @method Show\Field|Collection lock_user(string $label = null)
     * @method Show\Field|Collection mobile(string $label = null)
     * @method Show\Field|Collection pay_group_merchant_user_ids(string $label = null)
     * @method Show\Field|Collection pay_limit_max(string $label = null)
     * @method Show\Field|Collection pay_limit_min(string $label = null)
     * @method Show\Field|Collection self_add_bank(string $label = null)
     * @method Show\Field|Collection settlement_agent1_rate(string $label = null)
     * @method Show\Field|Collection settlement_agent2_rate(string $label = null)
     * @method Show\Field|Collection settlement_agent3_rate(string $label = null)
     * @method Show\Field|Collection settlement_agent4_rate(string $label = null)
     * @method Show\Field|Collection settlement_agent5_rate(string $label = null)
     * @method Show\Field|Collection settlement_user_rate(string $label = null)
     * @method Show\Field|Collection transfer_agent1_rate(string $label = null)
     * @method Show\Field|Collection transfer_agent2_rate(string $label = null)
     * @method Show\Field|Collection transfer_agent3_rate(string $label = null)
     * @method Show\Field|Collection transfer_agent4_rate(string $label = null)
     * @method Show\Field|Collection transfer_agent5_rate(string $label = null)
     * @method Show\Field|Collection transfer_balance_amount(string $label = null)
     * @method Show\Field|Collection transfer_notice(string $label = null)
     * @method Show\Field|Collection transfer_user_rate(string $label = null)
     * @method Show\Field|Collection user_group_id(string $label = null)
     * @method Show\Field|Collection zeros_balance(string $label = null)
     * @method Show\Field|Collection api_messages_count(string $label = null)
     * @method Show\Field|Collection app_id(string $label = null)
     * @method Show\Field|Collection peak_connections_count(string $label = null)
     * @method Show\Field|Collection websocket_messages_count(string $label = null)
     */
    class Show {}

    /**
     
     */
    class Form {}

}

namespace Dcat\Admin\Grid {
    /**
     * @method $this status(...$params)
     * @method $this google(...$params)
     * @method $this amount(...$params)
     * @method $this text(...$params)
     */
    class Column {}

    /**
     
     */
    class Filter {}
}

namespace Dcat\Admin\Show {
    /**
     
     */
    class Field {}
}
