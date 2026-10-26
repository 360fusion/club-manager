@if($notice !== '')
    <x-notice :variant="$noticeType === 'error' ? 'danger' : 'success'" dismiss="dismissNotice">{{ $notice }}</x-notice>
@endif
