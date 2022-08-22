<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static CREDIT()
 * @method static static DEBIT()
 */
final class TransactionType extends Enum
{
    const CREDIT = 'credit';
    const DEBIT = 'debit';
}
