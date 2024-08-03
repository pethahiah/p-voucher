<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsor extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'sponsor_name',
        'sponsor_registration_number',
        'sponsor_description',
        'isSponsorVerified',
        'user_id'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
