<?php

namespace App\Support;

/**
 * The file manager's built-in folders, so the same list isn't duplicated across
 * validation rules and the admin UI. Club-created folders (see MediaFolder) live
 * alongside these but are never allowed to reuse one of these identifiers.
 */
class MediaFolders
{
    /** @var list<string> */
    public const SYSTEM = [
        'logos', 'news', 'events', 'updates', 'newsletters',
        'pages', 'images', 'galleries', 'documents', 'accounting', 'summons',
    ];

    /** Folders that only accept image uploads. */
    public const IMAGE_ONLY = ['logos', 'images', 'galleries'];

    /** Words a club-created folder may never use: the system folders, plus the sidebar's virtual views. */
    public const RESERVED_WORDS = [...self::SYSTEM, 'all', 'trash'];

    /** System folders a club-created folder may nest inside. Excludes 'accounting': attachments there are
     *  created only by the Accounting module and are never browsable as an ordinary folder. */
    public const NESTABLE = [
        'logos', 'news', 'events', 'updates', 'newsletters',
        'pages', 'images', 'galleries', 'documents', 'summons',
    ];
}
