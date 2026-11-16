<?php

namespace Launchpad\Moderation;

/**
 * Content people can report (a post, a comment, a profile): the model implements this and is listed in config launchpad.moderation.types,
 * and the page shows <x-launchpad::report :subject="$post" /> (or the Vue ReportButton). Staff review reports in HQ.
 */
interface Reportable
{
    /** A short line that tells staff what it is: "Post: Summer sale by Ada" */
    public function reportLabel(): string;

    /** Where staff can see it, or null */
    public function reportUrl(): ?string;
}
