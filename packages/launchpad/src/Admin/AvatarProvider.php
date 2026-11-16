<?php

namespace Launchpad\Admin;

use Filament\AvatarProviders\Contracts\AvatarProvider as Provider;
use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Accounts\Avatar;

/** The admin's user menu shows the photo the person uploaded on their profile, and their initials until they have one */
class AvatarProvider implements Provider
{
    public function get(Model $record): string
    {
        return Avatar::url($record) ?? (new UiAvatarsProvider)->get($record);
    }
}
