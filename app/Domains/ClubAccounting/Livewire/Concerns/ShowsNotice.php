<?php

namespace App\Domains\ClubAccounting\Livewire\Concerns;

/**
 * A message shown at the top of the component itself. A session flash only appears on the next full page load,
 * which never comes after a wire:click, so actions report their outcome here instead.
 */
trait ShowsNotice
{
    public string $notice = '';

    public string $noticeType = 'success';

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->notice = $message;
        $this->noticeType = $type;
    }

    public function dismissNotice(): void
    {
        $this->notice = '';
    }
}
