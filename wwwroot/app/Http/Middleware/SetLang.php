<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class SetLang
{
    public function handle(Request $request, Closure $next)
    {
        $locale = (string) Cookie::get('locale', '');
        if ($locale !== '' && $this->isSupportedLocale($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function isSupportedLocale(string $locale): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $locale) === 1 && is_dir(lang_path($locale));
    }
}
