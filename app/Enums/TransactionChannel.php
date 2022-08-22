<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static WALLET()
 * @method static static VIRTUAL_CARD()
 * @method static static BANK_TRANSFER()
 * @method static static VIRTUAL_ACCOUNT()
 */
final class TransactionChannel extends Enum
{
    const WALLET = 'wallet';
    const VIRTUAL_CARD = 'virtual-card';
    const BANK_TRANSFER = 'bak-transfer';
    const VIRTUAL_ACCOUNT = 'virtual-account';
}
