<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * A request the ledger refuses for a reason the user can fix, such as an unbalanced journal or a closed financial year.
 * It is shown back to them as a message (see bootstrap/app.php) instead of a server error.
 */
class LedgerRuleException extends InvalidArgumentException {}
