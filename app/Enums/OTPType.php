<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static EmailVerification()
 * @method static static PinVerification()
 */
final class OTPType extends Enum
{
    const EmailVerification =   'email_verification';
    const PinVerification =   'pin_verification';
}
