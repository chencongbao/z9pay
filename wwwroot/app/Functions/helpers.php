<?php

use Illuminate\Support\Str;
use App\Models\DepositOrder;
use App\Jobs\TelegramQunSendJob;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use App\Extendtions\Logger\SimpleLogger;
use App\Services\Order\OrderCacheService;
use App\Extendtions\Dcat\Widgets\BobTable;
use App\Services\Enums\LoginExceptionTypeEnum;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Config\CacheAdminSettingService;
use App\Services\DepositOrder\DepositOrderPayAmountService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

if (!function_exists('bob_send_system_error_message')) {
    function bob_send_system_error_message($message = "hello")
    {
        try {
            if (empty($message)) return;

            if (config('app.debug')) {
                bob_newlog("错误信息", [$message], "error");
            }

            $name = (string)config('app.name', 'system');
            $message = is_array($message)
                ? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : (string)$message;

            // 清理不可见控制字符，避免异常内容影响消息发送。
            $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message ?? '');
            $message = str_replace(["\r\n", "\r"], "\n", (string)$message);
            $message = trim($message);

            if ($message === '') {
                return;
            }

            // Telegram Markdown 代码块中出现 ``` 会导致格式异常，统一替换。
            $message = str_replace('```', "'''", $message);
            $html = "```{$name}\n{$message}```\n";

            dispatch(new TelegramQunSendJob([
                'telegram_group_id' => config("default.system_telegram_id"),
                'send_content' => $html,
                'parse_mode' => 'Markdown',
                'is_telegram_failure_notice' => 1
            ]))->onQueue('notice');
        } catch (\Throwable $e) {

        }
    }
}


if (!function_exists('bob_send_system_user_login_exception_notice')) {
    function bob_send_system_user_login_exception_notice($username = "", $usertype = "")
    {
        $title = "系统用户登录异常报警";
        $content = "======‼️‼️📢" . $title . "🆘‼️‼️======\n";
        $content .= "系统端：" . LoginExceptionTypeEnum::label((string)$usertype) . "\n";
        $content .= "用户名：" . $username . "\n";
        $content .= "登录IP：" . bob_ip() . "\n";
        $content .= "登录时间：" . date('Y-m-d H:i:s') . "\n";
        bob_send_system_error_message($content);
        if (intval(bob_admin_setting("telegram_turn_on")) == 0 || intval(bob_admin_setting("user_login_exception_notice_switch")) == 0) return;
        $result = bob_format_muti_data_to_array(bob_admin_setting("user_login_exception_notice_telegram_group_ids"));
        if (!empty($result) && !empty($usertype) && !empty($username)) {
            $id = md5($usertype . $username . bob_ip());
            Cache::put(CacheConstPrefixService::TELEGRAM_LOGIN_EXCEPTION_BAN_INFO . $id, [
                'ip' => bob_ip(),
                'type' => LoginExceptionTypeEnum::blacklistType((string)$usertype),
                'username' => $username,
                'usertype' => LoginExceptionTypeEnum::label((string)$usertype),
            ], now()->addDay());
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "🚫封禁:1天", 'callback_data' => json_encode(["t" => 15, 'k' => $id, 'm' => 86400])],
                        ['text' => "🚫永久封禁", 'callback_data' => json_encode(["t" => 15, 'k' => $id, 'm' => 0])]
                    ]
                ],
            ];
            if (Cache::has(CacheConstPrefixService::SEND_CHANNEL_EXCEPTION_NOTICE . $id)) return;
            foreach ($result as $value) {
                if (empty($value)) {
                    continue;
                }
                dispatch(new TelegramQunSendJob([
                    'telegram_group_id' => $value,
                    'send_content' => $content,
                    'reply_markup' => $keyboard
                ]))->onQueue('notice');
            }
        }
    }
}

if (!function_exists('bob_send_channel_exception_notice')) {
    function bob_send_channel_exception_notice($message = [])
    {
        if (intval(bob_admin_setting("telegram_turn_on")) == 0 || intval(bob_admin_setting("telegram_channel_exception_notice_switch")) == 0) return;
        $result = bob_format_muti_data_to_array(bob_admin_setting("telegram_channel_exception_notice_telegram_group_ids"));
        if (!empty($result) && isset($message['action']) && !empty($message['action'])) {
            $title = "系统异常报警";
            if (isset($message["title"])) $title = $message["title"];
            $content = "======‼️‼️📢" . $title . "🆘‼️‼️======\n";
            if (isset($message["action"])) {
                $content .= "异常描述：" . $message["action"] . "\n";
            }
            if (isset($message["ordernumber"])) {
                $content .= "系统单号：`" . $message["ordernumber"] . "`\n";
            }
            if (isset($message["channel_name"])) {
                $content .= "渠道名称：" . $message["channel_name"] . "\n";
            }
            if (isset($message["error"]) && !empty($message["error"])) {
                $content .= "异常原因：" . $message["error"] . "\n";
            }
            $id = md5($message['action']);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "🔕静默:5分钟", 'callback_data' => json_encode(["type" => 6, 'key' => $id, 'time' => 300])],
                        ['text' => "🔕静默:15分钟", 'callback_data' => json_encode(["type" => 6, 'key' => $id, 'time' => 900])]
                    ]
                ],
            ];
            if (Cache::has(CacheConstPrefixService::SEND_CHANNEL_EXCEPTION_NOTICE . $id)) return;
            foreach ($result as $key => $value) {
                dispatch(new TelegramQunSendJob(['telegram_group_id' => $value, 'send_content' => $content, 'reply_markup' => $keyboard]))->onQueue('notice');
            }
        }
    }
}


if (!function_exists('bob_send_system_deposit_notice')) {
    function bob_send_system_deposit_notice($data = [])
    {
        $data['type'] = 1;
        bob_send_system_notice($data);
    }
}

if (!function_exists('bob_send_system_transfer_notice')) {
    function bob_send_system_transfer_notice($data = [])
    {
        $data['type'] = 2;
        bob_send_system_notice($data);
    }
}


if (!function_exists('bob_send_system_settlement_notice')) {
    function bob_send_system_settlement_notice($data = [])
    {
        $data['type'] = 3;
        bob_send_system_notice($data);
    }
}

if (!function_exists('bob_link')) {
    function bob_link($value, $link)
    {
        return '<a class="text-primary" href="' . $link . '"  style="text-decoration: underline;">' . $value . '</a>';
    }
}


if (!function_exists('bob_send_system_notice')) {
    function bob_send_system_notice($data = [])
    {

        $id = $data['id'] ?? 0;
        $type = $data['type'] ?? 0;

        if (intval(bob_admin_setting("notice_voice_on")) == 1) {

            if (isset($data['voice_id'])) {

                if ($type == 1) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_deposit_voice_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['voice_file' => asset("voice/" . $data['voice_id'] . ".mp3")]));
                    }
                }

                if ($type == 2) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_transter_voice_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['voice_file' => asset("voice/" . $data['voice_id'] . ".mp3")]));
                    }
                }

                if ($type == 3) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_settlement_voice_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['voice_file' => asset("voice/" . $data['voice_id'] . ".mp3")]));
                    }
                }


            }


        }


        if (intval(bob_admin_setting("notice_text_on")) == 1) {
            if (isset($data['error_text'])) {
                if ($type == 1) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_deposit_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['error_text' => $data['error_text']]));
                    }
                }

                if ($type == 2) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_transter_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['error_text' => $data['error_text']]));
                    }
                }

                if ($type == 3) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_settlement_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['error_text' => $data['error_text']]));
                    }
                }
            }

            if (isset($data['success_text'])) {
                if ($type == 1) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_deposit_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['success_text' => $data['success_text']]));
                    }
                }

                if ($type == 2) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_transter_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['success_text' => $data['success_text']]));
                    }
                }

                if ($type == 3) {
                    if (in_array($id, json_decode(bob_admin_setting("notice_settlement_text_notice"), true))) {
                        event(new \App\Events\SystemGloabelNoticeEvent(['success_text' => $data['success_text']]));
                    }
                }
            }
        }

    }
}


if (!function_exists('bob_create_appkey')) {
    function bob_create_appkey()
    {
        mt_srand((double)microtime() * 1000000);
        return substr(md5(time() . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT)), 8, 16);
    }
}

if (!function_exists('bob_create_app_secret')) {
    function bob_create_app_secret()
    {
        mt_srand((double)microtime() * 1000000);
        return md5(date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT));
    }

}


if (!function_exists('bob_ordernumber')) {
    /**
     * 生成订单号
     *
     * @param string $pre
     * @return string
     */
    function bob_ordernumber($pre = 'o')
    {
        mt_srand((double)microtime() * 1000000);
        return strtoupper($pre) . date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT) . bob_ordernumber_suffix();
    }
}


function bob_ordernumber_suffix()
{
    $model = \App\Models\Ordernumber::create([]);
    return $model->id;
}


if (!function_exists('bob_array_to_keyvalue')) {
    function bob_array_to_keyvalue($array = [])
    {
        $collection = collect($array);
        return $collection->where('status', 1)->pluck('name', 'id');
    }

}

if (!function_exists('bob_get_value_by_id_array')) {
    function bob_get_value_by_id_array($where = [], $value_name = '', $array = [])
    {
        $collection = collect($array);
        $result = $collection->firstWhere(array_key_first($where), $where[array_key_first($where)]);
        return optional($result)[$value_name];
    }
}


if (!function_exists('bob_newlog')) {
    function bob_newlog($msg = '', $context = [], $filename = 'laravel', $debug = false)
    {
        if (!config('app.debug') && !$debug) return;
        if (empty($msg)) return;
        SimpleLogger::$filename($msg, $context, "debug");
    }
}

if (!function_exists('bob_label_color')) {
    function bob_label_color($data = [], $defaultColor = [])
    {
        $result = [];
        $color = empty($defaultColor) ? ['success', 'danger', 'warning', 'info', 'primary', 'default'] : $defaultColor;
        if (!empty($data) && is_array($data)) {
            for ($i = 0; $i < count($data); $i++) {
                $result[$data[$i]] = $color[$i % 6];
            }
        }
        return $result;
    }
}

if (!function_exists('bob_get_rand_str')) {
    function bob_get_rand_str($len)
    {
        $chars = array(
            "0",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9"
        );
        $charsLen = count($chars) - 1;
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }
}

if (!function_exists('bob_lock')) {
    function bob_lock($str, $key = "1234123412ABCDEF", $iv = "ABCDEF1234123412")
    {
        return openssl_encrypt($str, 'AES-128-CBC', $key, 0, $iv);
    }
}


if (!function_exists('bob_unlock')) {
    function bob_unlock($str, $key = "1234123412ABCDEF", $iv = "ABCDEF1234123412")
    {
        return openssl_decrypt($str, 'AES-128-CBC', $key, 0, $iv);
    }
}


if (!function_exists('bob_invite_code')) {
    function bob_invite_code()
    {
        $is = false;
        $code = "";
        do {
            $code = mt_rand(100000, 999999);
            $vo = User::where('invite_code', $code)->first();
            if ($vo) {
                $is = true;
            } else {
                $is = false;
            }
        } while ($is);
        return $code;
    }
}

if (!function_exists('bob_admin_route')) {
    function bob_admin_route($name = "", $params = [], $admin = "admin")
    {
        return \Dcat\Admin\Admin::app()->getRoute($name, $params);
    }
}

if (!function_exists('bob_amount_format')) {
    function bob_amount_format($amount = 0, $precision = 2)
    {
        return floatval(round(floatval($amount), $precision));
    }
}


if (!function_exists('bob_show_table_info')) {
    //grid 显示表格信息
    function bob_show_table_info($data = [], $style = [], $bgColor = [], $defaultShowLine = 3)
    {
        $table = new BobTable([], $data, $style, $bgColor);
        $table->withBorder();
        if (count($data) > $defaultShowLine) {
            $table->setFold(true);
            $table->setDefaultLine($defaultShowLine);
        }
        $card = \Dcat\Admin\Widgets\Card::make($table->render());
        $card->withHeaderBorder()->noPadding()->style("margin-bottom:0px;display: inline;");
        return $card;
    }
}

if (!function_exists('bob_unit_format')) {
    function bob_unit_format($amount, $unit = "")
    {
        return bob_split_number(bob_amount_format($amount)) . $unit;
    }
}

if (!function_exists('bob_split_number')) {
    function bob_split_number($amount)
    {
        return preg_replace('/(?<=[0-9])(?=(?:[0-9]{3})+(?![0-9]))/', ',', $amount);
    }
}

if (!function_exists('bob_unsplit_number')) {
    function bob_unsplit_number($amount)
    {
        return floatval(str_replace(",", "", $amount));
    }
}


if (!function_exists('bob_sign')) {
    function bob_sign($data, $secretKey)
    {
        ksort($data);
        reset($data);
        $string = '';
        foreach ($data as $key => $val) {
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $string .= "{$key}={$val}&";
        }
        $string = trim($string, "&");
        $sign = base64_encode(hash_hmac('sha1', $string, $secretKey, true));
        return $sign;
    }
}


if (!function_exists('bob_sign_string')) {
    function bob_sign_string($data)
    {
        ksort($data);
        reset($data);
        $string = '';
        foreach ($data as $key => $val) {
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $string .= "{$key}={$val}&";
        }
        return trim($string, "&");
    }
}

if (!function_exists('bob_format_muti_data_to_array')) {
    function bob_format_muti_data_to_array($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(function ($item) {
                return trim((string)$item);
            }, $value), function ($item) {
                return $item !== '';
            }));
        }

        if ($value === null || $value === '') {
            return [];
        }

        $items = preg_split('/[\s,，=]+/u', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($items)) {
            return [];
        }

        return array_values(array_map('trim', $items));
    }
}


if (!function_exists('bob_admin_base_path')) {
    /**
     * Get admin url.
     *
     * @param string $path
     * @return string
     */
    function bob_admin_base_path($path = '', $module = 'admin')
    {
        $prefix = '/' . trim(config($module . '.route.prefix'), '/');

        $prefix = ($prefix == '/') ? '' : $prefix;

        $path = trim($path, '/');

        if (is_null($path) || strlen($path) == 0) {
            return $prefix ?: '/';
        }

        return $prefix . '/' . $path;
    }
}


if (!function_exists('bob_merchant_user_pid')) {

    function bob_merchant_user_pid()
    {
        $user = \Dcat\Admin\Admin::user();
        $pid = intval(optional($user)->offsetGet('pid'));
        if ($pid > 0) return $user->pid;
        return $user->id;
    }
}


if (!function_exists('bob_is_merchant_child_account')) {

    function bob_is_merchant_child_account()
    {
        $user = \Dcat\Admin\Admin::user();
        $pid = intval(optional($user)->offsetGet('pid'));
        if ($pid > 0) return true;
        return false;
    }
}


if (!function_exists('bob_str_replace')) {
    function bob_str_replace($string, $start = 3, $end = 4)
    {
        $string = (string)$string;
        $strlen = Str::length($string);
        if ($strlen <= 0) {
            return '';
        }

        $start = max(0, (int)$start);
        $end = max(0, (int)$end);
        if ($strlen <= $start + $end) {
            return mb_substr($string, 0, 1, 'UTF-8') . str_repeat('*', max(0, $strlen - 1));
        }

        $firstStr = mb_substr($string, 0, $start, 'UTF-8');
        $lastStr = Str::substr($string, '-' . $end);

        return $firstStr . str_repeat('*', max(0, $strlen - $start - $end)) . $lastStr;
    }
}

if (!function_exists('bob_merchant_deposit_float')) {
    function bob_merchant_deposit_float($number = 0, $type = 0, $is_need_decimal = 1)
    {
        if ($type == 0) return 0;
        if ($type == 1) {
            if (floatval($number) <= 1) return 0;
            if ($is_need_decimal == 0) {
                return intval(mt_rand(1, $number));
            }
            return mt_rand(1, $number) / 100;
        }
        if ($type == 2) {
            if (floatval($number) <= 1) return 0;
            if ($is_need_decimal == 0) {
                return intval(-mt_rand(1, $number));
            }
            return -mt_rand(1, $number) / 100;
        }
        return 0;
    }

}

if (!function_exists('bob_colors')) {
    function bob_colors()
    {
        return [\Dcat\Admin\Admin::color()->green(), \Dcat\Admin\Admin::color()->blue(), \Dcat\Admin\Admin::color()->success(), \Dcat\Admin\Admin::color()->info(), \Dcat\Admin\Admin::color()->danger(), \Dcat\Admin\Admin::color()->warning(), \Dcat\Admin\Admin::color()->dark(), \Dcat\Admin\Admin::color()->primary(), \Dcat\Admin\Admin::color()->custom(), \Dcat\Admin\Admin::color()->purple(), \Dcat\Admin\Admin::color()->cyan(), \Dcat\Admin\Admin::color()->red()];
    }
}


if (!function_exists('bob_show_label')) {
    //grid 显示表格信息
    function bob_show_label($str, $color = 0, $model = 1)
    {
        if ($str == '' || $str == null || $str < 0) return;
        if ($model == 2) {
            switch ($color) {
                case 3:
                    return '<span class="label bg-fuchsia margin-r-5">' . $str . '</span>';
                    break;
                case 4:
                    return '<span class="label bg-orange margin-r-5">' . $str . '</span>';
                    break;
                case 5:
                    return '<span class="label bg-green margin-r-5">' . $str . '</span>';
                    break;
                case 6:
                    return '<span class="label bg-red margin-r-5">' . $str . '</span>';
                    break;
                case 1:
                    return '<span class="label bg-cyan margin-r-5">' . $str . '</span>';
                    break;
                case 7:
                    return '<span class="label bg-dark margin-r-5">' . $str . '</span>';
                    break;
                default:
                    return '<span class="label bg-gray margin-r-5">' . $str . '</span>';
            }
            return;
        }
        if ($model == 3) {
            switch ($color) {
                case 3:
                    return '<span class="label bg-blue margin-r-5">' . $str . '</span>';
                    break;
                case 2:
                    return '<span class="label bg-fuchsia margin-r-5">' . $str . '</span>';
                    break;
                case 4:
                    return '<span class="label bg-green margin-r-5">' . $str . '</span>';
                    break;
                case 5:
                    return '<span class="label bg-red margin-r-5">' . $str . '</span>';
                    break;
                case 1:
                    return '<span class="label bg-cyan margin-r-5">' . $str . '</span>';
                    break;
                case 6:
                    return '<span class="label bg-dark margin-r-5">' . $str . '</span>';
                    break;
                default:
                    return '<span class="label bg-gray margin-r-5">' . $str . '</span>';
            }
            return;
        }
        switch ($color) {
            case 0:
                return '<span class="label bg-info margin-r-5">' . $str . '</span>';
                break;
            case 1:
                return '<span class="label bg-cyan margin-r-5">' . $str . '</span>';
                break;
            case 2:
                return '<span class="label bg-blue margin-r-5">' . $str . '</span>';
                break;
            case 3:
                return '<span class="label bg-danger margin-r-5">' . $str . '</span>';
                break;
            case 4:
                return '<span class="label bg-fuchsia margin-r-5">' . $str . '</span>';
                break;
            case 5:
                return '<span class="label bg-dark  margin-r-5">' . $str . '</span>';
                break;
            case 6:
                return '<span class="label bg-green margin-r-5">' . $str . '</span>';
                break;
            case 7:
                return '<span class="label  bg-teal margin-r-5">' . $str . '</span>';
                break;
            case 8:
                return '<span class="label  bg-olive margin-r-5">' . $str . '</span>';
                break;
            case 9:
                return '<span class="label bg-40 margin-r-5">' . $str . '</span>';
                break;
            case 10:
                return '<span class="label bg-35 margin-r-5">' . $str . '</span>';
                break;
            case 11:
                return '<span class="label bg-80  margin-r-5">' . $str . '</span>';
                break;
            case 12:
                return '<span class="label label-success bg-teal-active margin-r-5">' . $str . '</span>';
                break;
            case 13:
                return '<span class="label label-success bg-maroon  margin-r-5">' . $str . '</span>';
                break;
            case 14:
                return '<span class="label label-success bg-purple margin-r-5">' . $str . '</span>';
                break;
            case 15:
                return '<span class="label label-success bg-fuchsia margin-r-5">' . $str . '</span>';
                break;
            default:
                return '<span class="label label-warning bg-lime margin-r-5">' . $str . '</span>';
        }
    }
}


if (!function_exists('format_grid_line_muti_line_data')) {
    function format_grid_line_muti_line_data($data = "")
    {
        if (empty($data)) return;
        return bob_show_table_info(collect(bob_format_muti_data_to_array($data))->map(function ($item) {
            return [$item];
        })->all());
    }
}

if (!function_exists('bob_is_empty')) {
    function bob_is_empty($value)
    {
        return $value == '' || is_null($value);
    }
}

if (!function_exists('bob_string_to_list')) {
    function bob_string_to_list($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string)$value))));
    }
}

if (!function_exists('bob_ip_in_cidr')) {
    function bob_ip_in_cidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = intval($bits);
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainder)) & 0xff;
        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}

if (!function_exists('bob_is_cloudflare_ip')) {
    function bob_is_cloudflare_ip(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $cidrs = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

        foreach ($cidrs as $cidr) {
            if (bob_ip_in_cidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }
}


if (!function_exists('bob_ip')) {
    function bob_ip($debug = false)
    {
        $data = [];
        $ip = Request::ip();
        $host = strtolower((string)Request::getHost());
        $data['ip'] = $ip;
        $data['host'] = $host;
        $cloudflareDomains = array_map('strtolower', bob_string_to_list(config('default.client_ip_cf_domains')));
        if ($host !== '' && in_array($host, $cloudflareDomains, true)) {
            $data['cloudflare_domain'] = true;
            $data['cloudflare_proxy_ip'] = bob_is_cloudflare_ip($ip);
            $cloudflareIp = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? '';
            $data['HTTP_CF_CONNECTING_IP'] = $cloudflareIp;

            if ($data['cloudflare_proxy_ip'] && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
                $ip = $cloudflareIp;
            }

            $data['final_ip'] = $ip;
            if ($debug) {
                app(\App\Services\SystemNotice\SystemNoticeService::class)->debug('ip_debug', $data);
            }
            return $ip;
        }

        if (config('default.is_waf_on')) {
            if (isset($_SERVER["HTTP_X_REAL_IP"]) && !empty($_SERVER["HTTP_X_REAL_IP"])) {
                $ip = $_SERVER["HTTP_X_REAL_IP"];
                $data['HTTP_X_REAL_IP'] = $ip;
            }
        }
        $data['final_ip'] = $ip;
        if ($debug) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->debug('ip_debug', $data);
        }
        return $ip;
    }
}

if (!function_exists('bob_setDepositPayAmount')) {
    function bob_setDepositPayAmount($data = [])
    {
        App::make(DepositOrderPayAmountService::class)->applyByChannelData($data);
    }
}


if (!function_exists('bob_replacement_empty')) {

    function bob_replacement_empty($str)
    {
        return preg_replace('/\s+/', '', $str);
    }

}

if (!function_exists('bob_set_locale')) {
    function bob_set_locale($lang)
    {
        App::setLocale($lang);
    }
}


if (!function_exists('bob_send_exception_message')) {
    function bob_send_exception_message($e, $other = [])
    {
        bob_send_system_error_message(\Illuminate\Support\Arr::collapse([['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], $other]));
    }
}

if (!function_exists('bob_check_sign')) {
    function bob_check_sign($sign1, $sign2, $space = 0)
    {
        if ($space == 1) {
            $sign1 = str_replace(["+", " "], '', $sign1);
            $sign2 = str_replace(["+", " "], '', $sign2);
        }
        return hash_equals($sign1, $sign2);
    }
}

if (!function_exists('cache_deposit_info')) {
    function cache_deposit_info($model)
    {
        App::make(CacheDepositOrderInfoService::class)->cache($model);
        App::make(OrderCacheService::class)->putDeposit($model, true);
    }
}

if (!function_exists('cache_transfer_info')) {
    function cache_transfer_info($model)
    {
        App::make(OrderCacheService::class)->putTransfer($model);
    }
}

if (!function_exists('bob_settlement_time')) {
    function bob_settlement_time($settlement_mode = 0, $settlement_time = '17:00:00')
    {
        if ($settlement_mode == 0) return 0;
        if ($settlement_mode == 1) {
            $week = date('w', strtotime("+1 day"));
            if ($week == 6) {
                return strtotime(date("Y-m-d", strtotime("+3 day")) . " " . $settlement_time);
            }
            if ($week == 0) {
                return strtotime(date("Y-m-d", strtotime("+2 day")) . " " . $settlement_time);
            }
            return strtotime(date("Y-m-d", strtotime("+1 day")) . " " . $settlement_time);
        }
        if ($settlement_mode == 2) {
            $week = date('w', strtotime("+2 day"));
            if ($week == 6) {
                return strtotime(date("Y-m-d", strtotime("+4 day")) . " " . $settlement_time);
            }
            if ($week == 0) {
                return strtotime(date("Y-m-d", strtotime("+3 day")) . " " . $settlement_time);
            }
            return strtotime(date("Y-m-d", strtotime("+2 day")) . " " . $settlement_time);
        }
        return 0;
    }
}

if (!function_exists('bob_tree_to_array')) {
    function bob_tree_to_array($nodes)
    {
        $result = [];
        foreach ($nodes as $node) {
            $nodeArray = $node->toArray(); // 将节点对象转换为数组
            $children = $node->getChildren();
            if (!empty($children)) {
                $nodeArray['nodes'] = bob_tree_to_array($children); // 递归处理子节点
            }
            $result[] = $nodeArray;
        }
        return $result;
    }
}


if (!function_exists('bob_has_next_sibling')) {
    function bob_has_next_sibling($nodes, $parentId, $index)
    {
        foreach ($nodes as $i => $node) {
            if ($node['pid'] == $parentId && $i > $index) {
                return true;
            }
        }
    }
}


if (!function_exists('bob_build_select_options')) {
    function bob_build_select_options(array $nodes = [], $parentId = 0, $prefix = '', $space = '&nbsp;')
    {
        $d = '├─';
        $prefix = $prefix ?: $d . $space;

        $options = [];

        foreach ($nodes as $index => $node) {
            if ($node['pid'] == $parentId) {
                $currentPrefix = bob_has_next_sibling($nodes, $node['pid'], $index) ? $prefix : str_replace($d, '└─', $prefix);

                $node['bname'] = $currentPrefix . $space . $node['bname'];

                $childrenPrefix = str_replace($d, str_repeat($space, 6), $prefix) . $d . str_replace([$d, $space], '', $prefix);

                $children = bob_build_select_options($nodes, $node['id'], $childrenPrefix);

                $options[$node['id']] = $node['bname'];

                if ($children) {
                    $options += $children;
                }
            }
        }

        return $options;
    }
}

if (!function_exists('bob_percent')) {
    function bob_percent($num1 = 0, $num2 = 0)
    {
        if ($num2 == 0) return 0;
        $result = bob_amount_format($num1 / $num2);
        if ($result > 0) {
            return ($result * 100) . "%";
        }
        return 0;
    }
}

if (!function_exists('bob_admin_setting')) {
    function bob_admin_setting($name, $value = null)
    {
        return App::make(CacheAdminSettingService::class)->excute($name, $value, func_num_args() >= 2);
    }
}

if (!function_exists('bob_trim_zero')) {
    function bob_trim_zero($value)
    {
        rtrim(rtrim($value, '0'), '.');
    }
}

if (!function_exists('merchant_info_name')) {
    function merchant_info_name()
    {
        $info = \App\Models\MerchantInfo::where('merchant_user_id', bob_merchant_user_pid())->first(['name']);
        if ($info) {
            return $info->name;
        }
        return;
    }
}


if (!function_exists('admin_asset_versioned_path')) {
    function admin_asset_versioned_path(string $relativePath): string
    {
        $publicPath = public_path(ltrim($relativePath, '/'));

        if (!file_exists($publicPath)) {
            return $relativePath;
        }

        return $relativePath . '?v=' . filemtime($publicPath);
    }
}
