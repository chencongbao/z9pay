<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Luckypay 本地入口</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2f7;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        }

        .entry-card {
            width: min(560px, calc(100vw - 40px));
            padding: 32px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .12);
        }

        .entry-title {
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 700;
        }

        .entry-list {
            display: grid;
            gap: 12px;
            margin-top: 24px;
        }

        .entry-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #334155;
            text-decoration: none;
            transition: all .18s ease;
        }

        .entry-link:hover {
            border-color: #22c55e;
            color: #15803d;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, .12);
        }

        .entry-link span {
            color: #94a3b8;
        }
    </style>
</head>
<body>
<main class="entry-card">
    <h1 class="entry-title">Luckypay 本地入口</h1>

    <div class="entry-list">
        @foreach($entries as $entry)
            <a class="entry-link" href="{{ $entry['url'] }}">
                <strong>{{ $entry['name'] }}</strong>
                <span>进入</span>
            </a>
        @endforeach
    </div>
</main>
</body>
</html>
