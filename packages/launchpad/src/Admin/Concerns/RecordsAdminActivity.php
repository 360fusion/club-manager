<?php

namespace Launchpad\Admin\Concerns;

use Launchpad\Admin\AdminLog;

/** Use in a Filament Create or Edit page: what is saved is in the admin log (the secrets in it are masked). */
trait RecordsAdminActivity
{
    /** The action code the log files a save under; a page overrides it */
    protected function logAction(): string
    {
        return 'user.updated';
    }

    protected function afterSave(): void
    {
        AdminLog::saved($this->getRecord(), $this->logAction());
    }
}
