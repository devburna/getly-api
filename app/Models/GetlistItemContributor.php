<?php

namespace App\Models;

use App\Enums\GetlistItemContributionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GetlistItemContributor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'getlist_item_id',
        'reference',
        'full_name',
        'email_address',
        'phone_number',
        'type',
        'amount',
        'meta'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'getlist_item_id',
        'reference',
        'meta'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => GetlistItemContributionType::class
    ];

    public function gift(): BelongsTo
    {
        return $this->belongsTo(GetlistItem::class);
    }
}
