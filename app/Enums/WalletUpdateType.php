<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Credit()
 * @method static static Debit()
 */
final class WalletUpdateType extends Enum
{
    const Credit =   1;
    const Debit =   0;
}
