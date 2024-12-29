<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'description',
        'account_number',
        'bankName',
        'bankCode',
        'transactionDate',
        'merchantReference',
        'fiName',
        'paymentMethod',
        'payThruReference',
        'paymentReference',
        'responseCode',
        'responseDescription',
        'amount',
        'status',
        'commission',
        'residualAmount',
        'customerName',
        'resultCode',
    ];
}
