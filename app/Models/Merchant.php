<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use HasFactory, Softdeletes;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'voucher_code',

    ];


    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class, 'merchant_vouchers')
            ->withPivot('voucher_code')
            ->withTimestamps();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }




}
