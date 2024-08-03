<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'beneficiary_id',
        'merchant_id',
        'amount',
        'status',
        'code',
        'type',
        'code_generation_method',

    ];


}
