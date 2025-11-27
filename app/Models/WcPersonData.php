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
    protected static array $blacklistedPernrs = [];

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
