<?php

namespace App\Services\StaticSite;

use RuntimeException;

/**
 * A reason a website build or push stopped that is safe and useful to show the lodge as it is.
 */
class StaticSiteException extends RuntimeException {}
