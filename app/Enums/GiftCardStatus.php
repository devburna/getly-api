<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static CLAIMED()
 * @method static static REDEEMABLE()
 */
final class GiftCardStatus extends Enum
{
    const CLAIMED = 'claimed';
    const REDEEMABLE = 'redeemable';
}
