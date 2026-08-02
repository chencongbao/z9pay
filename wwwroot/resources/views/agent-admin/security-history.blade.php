<script id="agent-admin-security-history">
    window.addEventListener('pageshow', function (event) {
        var navigation = window.performance && window.performance.getEntriesByType
            ? window.performance.getEntriesByType('navigation')[0]
            : null;

        if (event.persisted || (navigation && navigation.type === 'back_forward')) {
            window.location.reload();
        }
    });
</script>
