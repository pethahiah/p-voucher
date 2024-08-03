<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, Softdeletes;


    protected $fillable = [
        'voucher_code', 'sponsor_id', 'purpose',
        'expiry_date', 'limit', 'type', 'code_generation_method', 'location', 'voucher_amount', 'amount_per_code'
    ];


    public function beneficiary(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function merchants()
    {
        return $this->belongsToMany(Merchant::class, 'merchant_vouchers')
            ->withPivot('voucher_code')
            ->withTimestamps();
    }

}

