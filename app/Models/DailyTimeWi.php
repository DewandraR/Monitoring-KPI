<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class DailyTimeWi extends Model
{
    protected $table = 'daily_time_wi';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nik' => 'string',
        'kode_laravel' => 'string',
        'total_time_wi' => 'float',
    ];

    // BLACKLIST NIK (kosongkan dulu)
    protected static array $blacklistedNiks = [
        // '10005800',
    ];

    public static function getBlacklistedNiks(): array
    {
        return self::$blacklistedNiks;
    }

    protected static function applyBlacklist(Builder $query): Builder
    {
        if (!empty(self::$blacklistedNiks)) {
            $query->whereNotIn('nik', self::$blacklistedNiks);
        }
        return $query;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('blacklist', function (Builder $builder) {
            static::applyBlacklist($builder);
        });
    }
}
