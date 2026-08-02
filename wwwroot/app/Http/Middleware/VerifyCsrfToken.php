<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'cashier/deposit/*',
        'cashier/transfer/*',
        'test',
        'telegram/webhook',
        'notice',
        "*/captcha/get",
        "*/captcha/check",
        "cashier/callback/url"
    ];
}
