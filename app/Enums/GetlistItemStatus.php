<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static UNFULFILLED()
 * @method static static CLAIMED()
 * @method static static REDEEMABLE()
 */
final class GetlistItemStatus extends Enum
{
    const UNFULFILLED = 'unfulfilled';
    const CLAIMED = 'claimed';
    const REDEEMABLE = 'redeemable';
}
