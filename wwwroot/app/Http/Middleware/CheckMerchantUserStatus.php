<?php

namespace App\Http\Middleware;

use App\Models\MerchantUser;
use Closure;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;

class CheckMerchantUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if(Admin::user()){
            $user = MerchantUser::where('status',1)->where('id',Admin::user()->id)->first(['id','session_id','pid']);
            if($user){
                if(empty($user->session_id)){
                    Admin::guard()->logout();
                    return redirect(admin_url('auth/login'));
                }
                if($user->pid > 0){
                    $parent = MerchantUser::where('id',$user->pid)->first(['id','session_id','status']);
                    if(!$parent || (int)$parent->status !== 1){
                        MerchantUser::where('id',$user->id)->update(['session_id'=>""]);
                        Admin::guard()->logout();
                        return redirect(admin_url('auth/login'));
                    }
                }
                return $next($request);
            }
            Admin::guard()->logout();
            return redirect(admin_url('auth/login'));
        }
        return $next($request);
    }
}
