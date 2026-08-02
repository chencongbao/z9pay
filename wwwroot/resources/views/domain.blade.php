<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <!-- 移动端适配 -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>线路可访问性检测</title>
    <style>
        body {
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,"Helvetica Neue",sans-serif;
            background:#f5f5f5;
            padding:30px;
            max-width: 100%;
            margin: 0 auto;
        }
        h2 {
            margin-bottom: 10px;
            font-size: 1.5em;
            line-height: 1.3;
        }
        .desc {
            color:#666;
            margin-bottom: 20px;
            font-size: 1.1em;
            line-height: 1.6;
        }
        table {
            width:100%;
            border-collapse: collapse;
            background:#fff;
            word-break: break-all;
        }
        th, td {
            border:1px solid #eee;
            padding:8px 10px;
            font-size:13px;
        }
        th { background:#fafafa; text-align:left; }
        .ok { color:#2e7d32; font-weight:600; }
        .fail { color:#c62828; font-weight:600; }
        .testing { color:#ff9800; }
        .copyable {
            cursor: pointer;
            color: #1a73e8;
        }
        .copyable:active {
            opacity: 0.6;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
            z-index: 9999;
        }

        @media (max-width: 700px) {
            body { padding: 10px; }
            h2 { font-size: 1.25em; }
            .desc { font-size: 1em; }
            th, td { font-size: 12px; padding: 6px 6px; }
        }
    </style>
</head>
<body>

<h2>线路可访问性检测</h2>
<p class="desc">
    点击任意域名即可复制。检测结果仅在你当前设备显示，不会上传到服务器。
</p>

<table>
    <thead>
    <tr>
        <th>域名（点击复制）</th>
        <th style="width:180px;">你的访问结果</th>
    </tr>
    </thead>
    <tbody id="line-body">
    @foreach($domains as $domain)
        <tr data-url="{{ $domain }}">
            <td class="copyable">{{ $domain }}</td>
            <td class="status testing">检测中…</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="toast" id="toast">已复制到剪贴板</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const rows = document.querySelectorAll('#line-body tr[data-url]');
        const toast = document.getElementById('toast');

        rows.forEach(row => {
            const rawUrl = row.dataset.url;
            const statusCell = row.querySelector('.status');
            const domainCell = row.querySelector('.copyable');

            // 点击域名复制当前行
            domainCell.addEventListener('click', () => {
                copyToClipboard(rawUrl);
                showToast();
            });

            let finalUrl = rawUrl;
            if (!/^https?:\/\//i.test(finalUrl)) {
                finalUrl = 'https://' + finalUrl;
            }

            checkOne(finalUrl, statusCell);
        });

        function copyToClipboard(text) {
            // 新API优先
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).catch(fallbackCopy);
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const input = document.createElement('input');
            document.body.appendChild(input);
            input.value = text;
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        function showToast() {
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 1500);
        }
    });

    // 检测函数
    function checkOne(url, statusCell, timeoutMs = 5000) {
        statusCell.textContent = '检测中…';
        statusCell.className = 'status testing';

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        const start = performance.now();

        fetch(url, { mode: 'no-cors', signal: controller.signal })
            .then(() => {
                clearTimeout(timer);
                const cost = Math.round(performance.now() - start);
                statusCell.textContent = '✅ 可访问 (' + cost + 'ms)';
                statusCell.className = 'status ok';
            })
            .catch(() => {
                clearTimeout(timer);
                const cost = Math.round(performance.now() - start);
                statusCell.textContent = '❌ 不可访问 (' + cost + 'ms)';
                statusCell.className = 'status fail';
            });
    }
</script>

</body>
</html>
