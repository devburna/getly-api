<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static CONTRIBUTE()
 * @method static static BUY()
 */
final class GetlistItemContributionType extends Enum
{
    const CONTRIBUTE = 'contribute';
    const BUY = 'buy';
}
