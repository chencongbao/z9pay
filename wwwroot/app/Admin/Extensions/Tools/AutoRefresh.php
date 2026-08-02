<?php

namespace App\Admin\Extensions\Tools;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Tools\AbstractTool;

class AutoRefresh extends AbstractTool
{
    private string $key;

    private int $defaultSeconds;

    public function __construct(string $key, int $defaultSeconds = 60)
    {
        $this->key = preg_replace('/[^a-zA-Z0-9_-]/', '-', $key) ?: 'default';
        $this->defaultSeconds = min(3600, max(10, $defaultSeconds));
    }

    public function render()
    {
        $id = 'admin-auto-refresh-' . $this->key;
        Admin::script($this->buildScript($id));

        return <<<HTML
<style>
.admin-auto-refresh{margin-right:8px;display:inline-flex;align-items:center;gap:7px;height:36px;padding:4px 5px 4px 11px;border:1px solid #5b6fb8;border-radius:4px;background:#fff;color:#43526a;font-size:14px;box-shadow:0 1px 2px rgba(31,45,61,.05)}
.admin-auto-refresh__label{margin:0 3px 0 0;display:inline-flex;align-items:center;gap:7px;font-weight:500;font-size:14px;line-height:1;cursor:pointer;white-space:nowrap}
.admin-auto-refresh__switch{margin:0!important;accent-color:#586cb3}
.admin-auto-refresh__status{display:none;align-items:center;gap:3px;padding:3px 9px;border-radius:11px;background:#edf8f3;color:#24966d;font-size:13px;line-height:20px;white-space:nowrap}
.admin-auto-refresh__status strong{min-width:16px;text-align:right;font-weight:600}
.admin-auto-refresh button{height:28px!important;padding:3px 10px!important;border-radius:3px!important;font-size:13px!important}
.admin-auto-refresh__setting{display:none;border-color:#d7deea!important;color:#53627a!important;background:#f8fafc!important}
.admin-auto-refresh__setting:hover{border-color:#aebbd0!important;background:#f2f5f9!important}
.admin-auto-refresh__interval{display:none;align-items:center;gap:5px}
.admin-auto-refresh__input{display:inline-flex;align-items:center;height:28px;border:1px solid #ccd6e4;border-radius:3px;background:#fff;overflow:hidden}
.admin-auto-refresh__seconds{width:55px!important;height:26px!important;padding:2px 4px!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-align:center;font-size:14px!important}
.admin-auto-refresh__unit{padding-right:8px;color:#7a8799;font-size:13px;white-space:nowrap}
.admin-auto-refresh__cancel{border-color:transparent!important;color:#7a8799!important;background:transparent!important}
@media(max-width:767px){.admin-auto-refresh{margin:4px 0;float:none!important}.admin-auto-refresh__status-text{display:none}}
</style>
<div id="{$id}" class="pull-right admin-auto-refresh">
    <label class="admin-auto-refresh__label">
        <input type="checkbox" class="admin-auto-refresh__switch"> 自动刷新
    </label>
    <span class="admin-auto-refresh__status"><strong>{$this->defaultSeconds}</strong><span class="admin-auto-refresh__status-text">秒后刷新</span></span>
    <button type="button" class="btn btn-white btn-xs admin-auto-refresh__setting">设置</button>
    <span class="admin-auto-refresh__interval">
        <span class="admin-auto-refresh__input">
            <input type="number" min="10" max="3600" step="1" class="form-control input-sm admin-auto-refresh__seconds" value="{$this->defaultSeconds}" title="刷新间隔秒数">
            <span class="admin-auto-refresh__unit">秒</span>
        </span>
        <button type="button" class="btn btn-primary btn-xs admin-auto-refresh__save">保存</button>
        <button type="button" class="btn btn-white btn-xs admin-auto-refresh__cancel">取消</button>
    </span>
</div>
HTML;
    }

    private function buildScript(string $id): string
    {
        $selector = json_encode('#' . $id);
        $storagePrefix = json_encode('admin_auto_refresh_' . $this->key);

        return <<<JS
(function () {
    var root = $({$selector});
    if (!root.length) {
        return;
    }

    var timerKey = '__adminAutoRefreshTimer_{$this->key}';
    var storagePrefix = {$storagePrefix};
    var enabledKey = storagePrefix + '_enabled';
    var secondsKey = storagePrefix + '_seconds';
    var minSeconds = 10;
    var maxSeconds = 3600;
    var remainingSeconds = 0;

    function intSeconds(value) {
        var seconds = parseInt(value, 10);
        if (isNaN(seconds)) {
            seconds = {$this->defaultSeconds};
        }

        return Math.min(maxSeconds, Math.max(minSeconds, seconds));
    }

    function clearTimer() {
        if (window[timerKey]) {
            clearInterval(window[timerKey]);
            window[timerKey] = null;
        }
    }

    function setStatus(enabled, seconds) {
        root.find('.admin-auto-refresh__status strong').text(seconds);
        root.find('.admin-auto-refresh__status').css('display', enabled ? 'inline-flex' : 'none');
        root.find('.admin-auto-refresh__setting').toggle(enabled);
    }

    function startTimer() {
        var enabled = localStorage.getItem(enabledKey) === '1';
        var seconds = intSeconds(localStorage.getItem(secondsKey) || root.find('.admin-auto-refresh__seconds').val());

        root.find('.admin-auto-refresh__switch').prop('checked', enabled);
        root.find('.admin-auto-refresh__seconds').val(seconds);
        root.find('.admin-auto-refresh__interval').hide();
        setStatus(enabled, seconds);
        clearTimer();

        if (enabled) {
            remainingSeconds = seconds;
            window[timerKey] = setInterval(function () {
                remainingSeconds--;
                if (remainingSeconds <= 0) {
                    window.location.reload();
                    return;
                }

                setStatus(true, remainingSeconds);
            }, 1000);
        }
    }

    root
        .off('.admin-auto-refresh')
        .on('change.admin-auto-refresh', '.admin-auto-refresh__switch', function () {
            localStorage.setItem(enabledKey, $(this).is(':checked') ? '1' : '0');
            startTimer();
        })
        .on('click.admin-auto-refresh', '.admin-auto-refresh__setting', function () {
            root.find('.admin-auto-refresh__interval').css('display', 'inline-flex');
            root.find('.admin-auto-refresh__status,.admin-auto-refresh__setting').hide();
            root.find('.admin-auto-refresh__seconds').trigger('focus').select();
        })
        .on('click.admin-auto-refresh', '.admin-auto-refresh__save', function () {
            localStorage.setItem(secondsKey, intSeconds(root.find('.admin-auto-refresh__seconds').val()));
            startTimer();
        })
        .on('click.admin-auto-refresh', '.admin-auto-refresh__cancel', function () {
            startTimer();
        })
        .on('keydown.admin-auto-refresh', '.admin-auto-refresh__seconds', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                root.find('.admin-auto-refresh__save').trigger('click');
            }
        });

    startTimer();
})();
JS;
    }
}
