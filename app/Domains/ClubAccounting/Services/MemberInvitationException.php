<?php

namespace App\Domains\ClubAccounting\Services;

use RuntimeException;

/**
 * A member cannot be invited (or their invitation changed) right now. The message is written for the admin to read.
 */
class MemberInvitationException extends RuntimeException {}
