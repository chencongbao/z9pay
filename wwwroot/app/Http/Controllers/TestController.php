<?php

namespace App\Http\Controllers;

use App\Rules\NoEmpty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Zxing\QrReader;

class TestController extends Controller
{
    public function index(Request $request)
    {
       return "ok";
    }

    public function test1(Request $request)
    {
        return view('test');
    }

    public function test2(Request $request)
    {
        return view('test1');
    }


    public function notice(Request $request){
        return 'ok';
    }

    public function ip(Request $request){
        return bob_ip();
    }

    public function query(Request $request)
    {
        return ['code' => 10000];
    }

    public function domain()
    {
        // 这里写死 20 个，你可以自己换成你的线路域名
        $domains = [
            'https://api1.starpay888.vip',
            'https://api.starpay888.vip',
            'https://api.startpay.vip',
            'https://api1.startpay.vip',
            'https://api3.phelotto.com',
            'https://api1.phelotto.com',
            'https://api.phelotto.com'
        ];
        return view('domain', compact('domains'));
    }
}
