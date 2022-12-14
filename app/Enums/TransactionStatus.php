<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static NEW()
 * @method static static SUCCESS()
 * @method static static PENDING()
 * @method static static FAILED()
 */
final class TransactionStatus extends Enum
{
    const NEW = 'new';
    const SUCCESS = 'success';
    const PENDING = 'pending';
    const FAILED = 'failed';
}
