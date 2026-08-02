@include('admin.SelfChannel.partials.filters')

@include('admin.SelfChannel.partials.queue-table')

<script>
    (function () {
        var currentPaymentId = {{ (int) $paymentId }};
        var reloading = false;
        var selfChannelSocketBound = false;

        function selfChannelReload() {
            if (reloading) {
                return;
            }
            reloading = true;
            setTimeout(function () {
                var url = new URL(window.location.href);
                url.searchParams.set('_refresh', String(Date.now()));
                window.location.href = url.toString();
            }, 300);
        }

        function initSelfChannelSocket() {
            if (selfChannelSocketBound) {
                return;
            }

            if (!window.Echo) {
                setTimeout(initSelfChannelSocket, 1000);
                return;
            }

            selfChannelSocketBound = true;
            window.Echo.channel('system').listen('.self-channel-refresh', function (data) {
                if (!data) {
                    return;
                }

                if (parseInt(data.payment_id || 0) !== currentPaymentId) {
                    return;
                }

                selfChannelReload();
            });
        }

        initSelfChannelSocket();

        if (window.addEventListener) {
            window.addEventListener('load', initSelfChannelSocket, false);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    initSelfChannelSocket();
                }
            }, false);
        } else if (window.attachEvent) {
            window.attachEvent('onload', initSelfChannelSocket);
        }
    })();
</script>
