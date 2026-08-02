<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Admin;

class ForbiddenController
{
    public function auth()
    {
        if (Admin::guard()->guest()) {
            return redirect(admin_url('auth/login'));
        }

        $title = e(__('admin.merchant_admin_title'));
        $message = e(__('admin.deny'));

        return response(<<<HTML
<!doctype html>
<html lang="{$this->htmlLang()}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f5f9;color:#2f3a4a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
        .forbidden-box{width:min(420px,calc(100% - 32px));padding:34px 30px;border-radius:14px;background:#fff;box-shadow:0 18px 45px rgba(35,48,76,.12);text-align:center}
        .forbidden-code{font-size:46px;font-weight:700;letter-spacing:.04em;color:#4f6fb8;margin-bottom:10px}
        .forbidden-message{font-size:18px;font-weight:600}
    </style>
</head>
<body>
    <main class="forbidden-box">
        <div class="forbidden-code">403</div>
        <div class="forbidden-message">{$message}</div>
    </main>
</body>
</html>
HTML, 403);
    }

    private function htmlLang(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }
}
