@php
    $assets = [
        'luckypay' => 'resources/js/app-luckypay.js',
        'shpay' => 'resources/js/app-shpay.js',
        'sgpay' => 'resources/js/app-sgpay.js',
        'apluspay' => 'resources/js/app-apluspay.js',
        'haoyunlai' => 'resources/js/app-haoyunlai.js',
        'z9pay' => 'resources/js/app-z9pay.js',
        'lupay' => 'resources/js/app-lupay.js',
        'lixiangpay' => 'resources/js/app-lixiangpay.js',
        'oro7pay' => 'resources/js/app-oro7pay.js',
        'rdspay' => 'resources/js/app-rdspay.js',
        'infinitepay' => 'resources/js/app-infinitepay.js',
        'phpay' => 'resources/js/app-phpay.js',
        'thuyphatpay' => 'resources/js/app-thuyphatpay.js',
        'nnpay' => 'resources/js/app-nnpay.js',
        'huiqianjinpay' => 'resources/js/app-huiqianjinpay.js',
        'apay' => 'resources/js/app-apay.js',
        'tp88pay' => 'resources/js/app-tp88pay.js',
    ];
    $asset = $assets[payment_app_name()] ?? null;
    $shouldLoadEcho = !config('iframe_tab.enable') || !request()->boolean('iframe_tab_child');
@endphp

@if($shouldLoadEcho && $asset)
    @vite($asset)
@endif
