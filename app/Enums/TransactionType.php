<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Success()
 * @method static static Failed()
 * @method static static Cancelled()
 */
final class TransactionType extends Enum
{
    const Pending =   'pending';
    const Success =   'successful';
    const Failed =   'failed';
    const Cancelled =   'cancelled';
}
