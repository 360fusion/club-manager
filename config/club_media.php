<?php

/**
 * Media file manager settings.
 *
 * `default_storage_quota_mb` applies to every club unless it sets its own
 * `settings.storage_quota_mb`. Null/absent means unlimited.
 */
return [

    'default_storage_quota_mb' => env('CLUB_MEDIA_DEFAULT_QUOTA_MB', 5120),

];
