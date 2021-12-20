<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gift extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'getlist_id',
        'reference',
        'name',
        'price',
        'quantity',
        'short_message',
        'image_url',
        'link',
        'receiver_name',
        'receiver_email',
        'receiver_phone',
        'sent_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'user_id',
        'getlist_id',
        'deleted_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'event_date' => 'datetime',
        'privacy' => 'boolean'
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(Contributor::class, 'gift_id');
    }

    public function getSentByAttribute($value)
    {
        $sender = User::find($value);

        return [
            'name' => $sender->name,
            'email' => $sender->email,
            'phone' => $sender->phone,
        ];
    }
}
