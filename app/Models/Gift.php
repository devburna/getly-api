<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'getlist_id',
        'name',
        'price',
        'quantity',
        'short_message',
        'image',
        'link',
        'receiver_name',
        'receiver_email',
        'receiver_phone',
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
}
