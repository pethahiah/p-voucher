<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'state',
    ];

    public function vouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
