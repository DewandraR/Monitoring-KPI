<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NikKorlap extends Model
{
    protected $table = 'nik_korlap';

    protected $fillable = [
        'nik',
        'nama',
        'plant',
        'wc_korlap',
    ];

    protected $casts = [
        'wc_korlap' => 'array',
    ];
}
