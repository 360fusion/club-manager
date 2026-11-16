@if (session('status'))
    <div class="lp-alert" role="status">{{ __(session('status')) }}</div>
@endif
