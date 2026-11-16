<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Api\WorkspaceApiController;

/*
| Loaded under /api/v1 (config launchpad.modules.api). Every route is for one workspace, found from the bearer token by `launchpad.api`, so a
| route never has to ask which workspace: app(CurrentWorkspace::class)->workspace. Add your own with `launchpad.api:ability` where a token
| needs an ability (the list of abilities is config launchpad.api.abilities). See docs/api-and-webhooks.md.
*/
// counted as the team's api_calls when that meter is listed (config launchpad.metering.meters)
$metered = array_key_exists('api_calls', (array) config('launchpad.metering.meters')) ? ['meter:api_calls'] : [];

Route::get('workspace', [WorkspaceApiController::class, 'workspace'])->middleware(['launchpad.api:read', ...$metered])->name('launchpad.api.workspace');
Route::get('members', [WorkspaceApiController::class, 'members'])->middleware(['launchpad.api:read', ...$metered])->name('launchpad.api.members');
