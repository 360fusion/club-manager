{{-- Added to a phone's home screen, the member area opens full screen like the app (public/manifest.json). --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#17264d">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Club Manager">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<script>
    // Opened from the home screen: draw under the status bar like the app does (.web-app in app.css).
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) {
        document.documentElement.classList.add('web-app');
    }
</script>
