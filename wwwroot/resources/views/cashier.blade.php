<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">
    <title>{{ __('cashier.title') }}</title>
    <script>
        window.__APP_CONFIG__ = Object.freeze(@json($config));
    </script>
</head>
<body>
<div id="app">
    <div class="cashier-loading">
        {{ __('cashier.title') }}
    </div>
</div>
<noscript>
    <div class="cashier-fallback">
        Please enable JavaScript and reopen the cashier page.
    </div>
</noscript>
<script nomodule>
    document.documentElement.className += ' legacy-webview';
</script>
<style>
    .cashier-loading,
    .cashier-fallback {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        box-sizing: border-box;
        color: #333;
        font-family: Arial, sans-serif;
        text-align: center;
    }
</style>
@vite('resources/js/app.js')
</body>
</html>
