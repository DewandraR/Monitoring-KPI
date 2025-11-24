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
    protected static $blacklistedPernrs = [
        "10000011",
        "10000015",
        "10000040",
        "10000062",
        "10000063",
        "10000083",
        "10000110",
        "10000126",
        "10000144",
        "10000161",
        "10000189",
        "10000364",
        "10000395",
        "10000414",
        "10000417",
        "10000427",
        "10000431",
        "10000440",
        "10000458",
        "10000482",
        "10000502",
        "10000524",
        "10000541",
        "10000548",
        "10000555",
        "10000564",
        "10000570",
        "10000577",
        "10000591",
        "10000615",
        "10000622",
        "10000642",
        "10000659",
        "10000725",
        "10000778",
        "10000874",
        "10001561",
        "10001983",
        "10002308",
        "10002690",
        "10002787",
        "10003007",
        "10003008",
        "10003009",
        "10004908",
        "10004934",
        "10004994",
        "10005063",
        "10003590",
        "10003599",
        "10003600",
        "10004874",
        "10000897",
        "10000898",
        "10001002",
        "10005271",
        "10006163",
        "10006337",
        "10007161",
        "10007473",
        "10007488",
        "10007854",
        "10007880",
        "10008015",
        "10002446",
        "10000467",
        "10004411",
        "10000644",
        "10000026",
        "10000093",
        "10000109",
        "10000112",
        "10000141",
        "10000266",
        "10000319",
        "10002804",
        "10000420",
        "10005689",
        "10008126",
        "10008135",
        "10008134",
    ];


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
