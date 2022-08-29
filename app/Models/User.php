<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email_address',
        'email_address_verified_at',
        'phone_number',
        'phone_number_verified_at',
        'avatar_url',
        'date_of_birth',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_address_verified_at' => 'datetime',
        'phone_number_verified_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    /**
     * Route notifications for the mail channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return array|string
     */
    public function routeNotificationForMail($notification)
    {
        // Return email address only...
        return $this->email_address;

        // Return email address and name...
        return [$this->email_address => $this->first_name];
    }

    /**
     * Route notifications for the Vonage channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return string
     */
    public function routeNotificationForVonage($notification)
    {
        return $this->phone_number;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function getlists(): HasMany
    {
        return $this->hasMany(Getlist::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function giftsSent(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'sender_id');
    }

    public function giftsReceived(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'user_id');
    }

    public function monoAccountHolder(): HasOne
    {
        return $this->hasOne(MonoAccountHolder::class);
    }

    public function virtualCard(): HasOne
    {
        return $this->hasOne(VirtualCard::class);
    }

    public function virtualAccount(): HasOne
    {
        return $this->hasOne(VirtualAccount::class);
    }

    public function debit($amount)
    {
        $current_balance = ((int)$this->wallet->current_balance - (int)$amount);
        $previous_balance = $this->wallet->current_balance;

        $this->wallet->update([
            'previous_balance' => $previous_balance < 0 ? 0.00 : $previous_balance,
            'current_balance' => $current_balance < 0 ? 0.00 : $current_balance,
        ]);
    }

    public function credit($amount)
    {
        return $this->wallet->update([
            'previous_balance' => $this->wallet->current_balance,
            'current_balance' => $this->wallet->current_balance + $amount,
        ]);
    }

    public function hasFunds($amount)
    {
        if ($amount > $this->wallet->current_balance) {
            return false;
        }

        return true;
    }
}
