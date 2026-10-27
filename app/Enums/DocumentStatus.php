<?php

namespace App\Enums;

/**
 * Where an invoice or a vendor bill is in its life. Both kinds share the same states and
 * rules (a draft is not posted, a pending_approval item waits for a second approver),
 * and the column stays a plain string so the values compare with the database as before.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Void = 'void';
    case Refunded = 'refunded';
}
