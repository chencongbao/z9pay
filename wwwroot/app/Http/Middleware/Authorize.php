<?php

namespace App\Http\Middleware;

use Dcat\Admin\Admin;

class Authorize
{
    /**
     * Authorize the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        return Admin::user() ?  (Admin::user()->isAdministrator() ? $next($request) : abort(403)) : abort(403);
    }
}
