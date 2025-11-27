<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Model untuk merepresentasikan tabel yppr058_data.
 */
class ReportData extends Model
{
    use HasFactory;

    protected $table = 'yppr058_data';

    protected $primaryKey = 'pernr';
    public $timestamps = false;

    // PENTING: Untuk memastikan Livewire mengirim NIK sebagai string saat diklik.
    protected $casts = [
        'pernr' => 'string',
    ];

    // Daftar NIK yang harus dikecualikan (BLACKLIST)
    protected static $blacklistedPernrs = [];


    /**
     * Scope untuk menerapkan Blacklist pada setiap query.
     */
    protected static function applyBlacklist(Builder $query)
    {
        return $query->whereNotIn('pernr', self::$blacklistedPernrs);
    }


    /**
     * Menerapkan list secara global.
     */
    protected static function booted()
    {
        static::addGlobalScope('blacklist', function (Builder $builder) {
            static::applyBlacklist($builder);
        });
    }

    /**
     * Scope untuk memfilter data berdasarkan input dari form.
     */
    public function scopeFilter(Builder $query, $pernr, $arbpl, $werks)
    {
        // Filter NIK (pernr)
        if ($pernr) {
            $query->where('pernr', 'LIKE', '%' . $pernr . '%');
        }

        // Filter WC Personal (arbpl)
        if ($arbpl) {
            $query->where('arbpl', 'LIKE', '%' . $arbpl . '%');
        }

        // Filter Plant (werks)
        if ($werks) {
            $query->where('werks', 'LIKE', '%' . $werks . '%');
        }

        return $query;
    }
}
