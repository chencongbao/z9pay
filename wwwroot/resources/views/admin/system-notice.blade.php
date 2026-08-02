@include('admin.partials.echo-app-script')
<script>
    if (window.addEventListener) {
        window.addEventListener("load", _notify_init, false);
    } else if (window.attachEvent) {
        window.attachEvent("onload", _notify_init);
    }

    function _notify_init() {
        if (window.top !== window || window.__systemNoticeInitialized) {
            return;
        }

        if (!window.Echo || typeof window.Echo.channel !== 'function') {
            setTimeout(_notify_init, 300);
            return;
        }

        window.__systemNoticeInitialized = true;

        var bgAudio = new audioController();
        @if(intval(bob_admin_setting("notice_voice_on")) == 1 && !$notice_voice_on)
            Dcat.confirm('开启系统语音通知?', null, function () {
                $.cookie('notice_voice_on', 1, {expires: 365, path: '/'});
                bgAudio.play("{{asset("voice/default.mp3")}}");
            });
        @endif
        window.Echo.channel('system').listen('.notice', function (data) {
            if (data.voice_file) {
                bgAudio.play(data.voice_file);
            }
            if (data.success_text) {
                notice_success(data.success_text);

            }
            if (data.error_text) {
                notice_error(data.error_text);
            }
        });
    }
</script>
