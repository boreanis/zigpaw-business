<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    try {
        const theme = localStorage.getItem('zigpaw-business-theme');
        if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme;
        else delete document.documentElement.dataset.theme;
    } catch {}
</script>
