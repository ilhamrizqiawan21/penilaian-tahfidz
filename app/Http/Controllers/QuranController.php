<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuranController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $dataset = DB::table('quran_datasets')->where('validation_status', 'active')->orderByDesc('updated_at')->first();
        $edition = $dataset ? DB::table('mushaf_editions')->where('dataset_id', $dataset->id)->where('status', 'active')->first() : null;

        if (! $dataset || ! $edition) {
            return Inertia::render('Quran/Reader', [
                'reference' => null,
                'surahs' => [],
                'selected_surah' => null,
                'ayahs' => [],
            ]);
        }

        $surahs = DB::table('surahs')->where('dataset_id', $dataset->id)->orderBy('number')
            ->get(['id', 'number', 'name_local', 'ayah_count']);
        $selectedNumber = max(1, min(114, (int) $request->query('surah', 1)));
        $selected = $surahs->firstWhere('number', $selectedNumber) ?? $surahs->first();
        $ayahs = $selected ? DB::table('ayahs')->where('surah_id', $selected->id)->orderBy('number')
            ->get(['id', 'number', 'global_order', 'text_uthmani']) : collect();
        $words = DB::table('edition_words')->where('edition_id', $edition->id)->whereIn('ayah_id', $ayahs->pluck('id'))
            ->where('token_kind', 'word')->orderBy('position')->get(['id', 'ayah_id', 'position', 'text_or_glyph'])->groupBy('ayah_id');
        $ayahs->each(fn ($ayah) => $ayah->words = $words->get($ayah->id, collect()));

        return Inertia::render('Quran/Reader', [
            'reference' => [
                'name' => $edition->name,
                'source_name' => $dataset->source_name,
                'source_url' => $dataset->source_url,
                'version' => $dataset->version,
                'riwayah' => $dataset->riwayah,
                'license_metadata' => $dataset->license_metadata,
                'is_demo' => str_contains(strtolower($dataset->source_name), 'sintetis') || strtolower($dataset->riwayah) === 'synthetic',
            ],
            'surahs' => $surahs->map(fn ($surah) => [
                'number' => $surah->number,
                'name_local' => $surah->name_local,
                'ayah_count' => $surah->ayah_count,
            ])->values(),
            'selected_surah' => $selected ? [
                'number' => $selected->number,
                'name_local' => $selected->name_local,
                'ayah_count' => $selected->ayah_count,
            ] : null,
            'ayahs' => $ayahs,
        ]);
    }
}
