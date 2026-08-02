<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $iframeTabCssPath = public_path('vendor/iframe-tab/css/style.css');
    $iframeTabSwiperCssPath = public_path('vendor/iframe-tab/css/swiper.min.css');
    $iframeTabCssVersion = file_exists($iframeTabCssPath) ? filemtime($iframeTabCssPath) : time();
    $iframeTabSwiperCssVersion = file_exists($iframeTabSwiperCssPath) ? filemtime($iframeTabSwiperCssPath) : time();
@endphp

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="chrome=1,IE=edge">
    {{-- 默认使用谷歌浏览器内核--}}
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    <title>{{ Dcat\Admin\Admin::title() }} @if(! empty($header)) | {{ $header }}@endif</title>

    @if(! config('admin.disable_no_referrer_meta'))
        <meta name="referrer" content="strict-origin-when-cross-origin"/>
    @endif

    @if(! empty($favicon = Dcat\Admin\Admin::favicon()))
        <link rel="shortcut icon" href="{{ $favicon }}">
    @endif

    {!! admin_section(Dcat\Admin\Admin::SECTION['HEAD']) !!}

    {!! Dcat\Admin\Admin::asset()->headerJsToHtml() !!}

    {!! Dcat\Admin\Admin::asset()->cssToHtml() !!}
    <link rel="stylesheet" href="{{ asset('/vendor/iframe-tab/css/swiper.min.css') }}?v={{ $iframeTabSwiperCssVersion }}">
    <link rel="stylesheet" href="{{ asset('/vendor/iframe-tab/css/style.css') }}?v={{ $iframeTabCssVersion }}">
    <style>
        #iframe-tab-container #iframe-tab .nav-link.active,
        #iframe-tab-container #iframe-tab .nav-link[aria-selected="true"] {
            background: #eefaf3 !important;
            background-image: none !important;
            background-color: #eefaf3 !important;
            color: #249c68 !important;
            box-shadow: inset 0 -3px 0 #2fb37a !important;
            border-right-color: #d7efe2 !important;
            font-weight: 600 !important;
        }

        #iframe-tab-container #iframe-tab .nav-link.active,
        #iframe-tab-container #iframe-tab .nav-link.active *,
        #iframe-tab-container #iframe-tab .nav-link[aria-selected="true"],
        #iframe-tab-container #iframe-tab .nav-link[aria-selected="true"] * {
            color: #249c68 !important;
        }
    </style>
</head>

@extends('iframe-tab::vertical')
