{{-- Opened from the home screen (the member area added there), a page is drawn under the status bar like the app, so
     .web-app (app.css) moves it clear of the clock: member pages and the club websites reached from them alike. --}}
<script>
    // Opened from the home screen: draw under the status bar like the app does (.web-app in app.css).
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) {
        document.documentElement.classList.add('web-app');
    }
</script>
