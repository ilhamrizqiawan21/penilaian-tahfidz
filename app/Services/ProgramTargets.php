<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramTargets
{
    public function activeDataset(): object
    {
        $dataset = DB::table('quran_datasets')->where('validation_status', 'active')->first();
        if (! $dataset) {
            throw ValidationException::withMessages(['dataset' => 'Referensi mushaf tervalidasi belum tersedia.']);
        }

        return $dataset;
    }

    public function resolve(string $datasetId, array $ranges): array
    {
        $resolved = [];
        foreach ($ranges as $index => $range) {
            $start = $this->ayah($datasetId, $range['startSurah'], $range['startAyah']);
            $end = $this->ayah($datasetId, $range['endSurah'], $range['endAyah']);
            if (! $start || ! $end || $start->global_order > $end->global_order) {
                throw ValidationException::withMessages(["ranges.$index" => 'Rentang ayat tidak valid atau terbalik.']);
            }
            $resolved[] = [
                'start_ayah_id' => $start->id,
                'end_ayah_id' => $end->id,
                'start_order' => $start->global_order,
                'end_order' => $end->global_order,
                'count' => $end->global_order - $start->global_order + 1,
            ];
        }

        return $resolved;
    }

    public function uniqueCount(array $ranges): int
    {
        usort($ranges, fn ($a, $b) => $a['start_order'] <=> $b['start_order']);
        $total = 0;
        $lastEnd = 0;
        foreach ($ranges as $range) {
            $total += max(0, $range['end_order'] - max($lastEnd, $range['start_order'] - 1));
            $lastEnd = max($lastEnd, $range['end_order']);
        }

        return $total;
    }

    private function ayah(string $datasetId, int $surahNumber, int $ayahNumber): ?object
    {
        return DB::table('ayahs')->join('surahs', 'surahs.id', '=', 'ayahs.surah_id')
            ->where('ayahs.dataset_id', $datasetId)->where('surahs.dataset_id', $datasetId)
            ->where('surahs.number', $surahNumber)->where('ayahs.number', $ayahNumber)
            ->first(['ayahs.id', 'ayahs.global_order']);
    }
}
