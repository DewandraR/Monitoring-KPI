<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WcPersonData extends Model
{
    protected $table = 'wc_person_data';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $casts = [
        'pernr' => 'string',
    ];

    /** Kolom-kolom yang dicari (arbid DIHAPUS) */
    public static array $searchable = [
        'otype',
        'objid',
        'pernr',
        'begda',
        'endda',
        'short',
        'stext',
        'arbpl',
        'desc',
        'role',
        'devisi',
    ];

    /** ====== BLACKLIST NIK (global) ====== */
    protected static array $blacklistedPernrs = [
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

    protected static function applyBlacklist(Builder $query): Builder
    {
        return $query->whereNotIn('pernr', self::$blacklistedPernrs);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('blacklist', function (Builder $builder) {
            static::applyBlacklist($builder);
        });
    }

    /**
     * Pencarian:
     * - Frasa dalam tanda kutip => cocokkan hanya ke short/stext (nama).
     * - Jika input tampak seperti nama multi-kata (tanpa angka), perlakukan sebagai frasa nama.
     * - Token lain => OR ke semua kolom $searchable (tanpa arbid).
     */
    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        $q = trim((string) $q);
        if ($q === '') return $query;

        // Frasa ber-kutip
        preg_match_all('/"([^"]+)"/u', $q, $m);
        $phrases = collect($m[1] ?? [])
            ->map(fn($p) => mb_strtolower(trim($p)))
            ->filter()
            ->values();

        // Sisa token (tanpa frasa ber-kutip)
        $qNoPhrases = preg_replace('/"([^"]+)"/u', ' ', $q);
        $tokens = collect(preg_split('/[\s,]+/u', $qNoPhrases))
            ->filter()
            ->map(fn($t) => mb_strtolower(trim($t)))
            ->values();

        // Deteksi nama/deskripsi multi-kata tanpa angka → treat sebagai frasa
        if ($phrases->isEmpty() && preg_match('/^[\p{L}\s\.]+$/u', $q)) {
            preg_match_all('/\p{L}+/u', $q, $words);
            if (count($words[0]) >= 2) {
                $phrases = collect([mb_strtolower($q)]);
            }
        }

        return $query->where(function (Builder $outer) use ($tokens, $phrases) {

            // === FRASA DALAM KUTIP ("...") ===
            foreach ($phrases as $p) {
                $outer->orWhere(function (Builder $qq) use ($p) {
                    $qq->whereRaw('LOWER(short) LIKE ?', ["%{$p}%"])
                        ->orWhereRaw('LOWER(stext) LIKE ?', ["%{$p}%"])
                        // desc pakai backtick karena reserved word
                        ->orWhereRaw('LOWER(`desc`) LIKE ?', ["%{$p}%"])
                        // ⬇⬇⬇ TAMBAHAN: devisi juga ikut
                        ->orWhereRaw('LOWER(devisi) LIKE ?', ["%{$p}%"]);
                });
            }

            // === TOKEN UMUM (tanpa kutip) ===
            foreach ($tokens as $t) {
                $outer->orWhere(function (Builder $qq) use ($t) {
                    foreach (self::$searchable as $col) {
                        if ($col === 'desc') {
                            $qq->orWhereRaw('LOWER(`desc`) LIKE ?', ["%{$t}%"]);
                        } else {
                            $qq->orWhereRaw("LOWER($col) LIKE ?", ["%{$t}%"]);
                        }
                    }
                });
            }
        });
    }
}
