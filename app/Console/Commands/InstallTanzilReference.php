<?php

namespace App\Console\Commands;

use App\Services\TanzilReferenceSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class InstallTanzilReference extends Command
{
    protected $signature = 'quran:install-tanzil {--activate : Jadikan sumber baru sebagai referensi aktif}';

    protected $description = 'Unduh, verifikasi, dan pasang teks Uthmani Tanzil v1.1';

    private const TEXT_URL = 'https://tanzil.net/pub/download/index.php?quranType=uthmani&outType=txt-2&agree=true';

    private const METADATA_URL = 'https://tanzil.net/res/text/metadata/quran-data.xml';

    private const TEXT_SHA256 = 'bf4f57b968d03f4131c070b1e285da9be0e0a108a21c910e872801ca273312c8';

    private const METADATA_SHA256 = '8867c1d88191472adec9db694b3cd9f135b1a2ef580574d32cf888dcb22c5c7a';

    public function handle(TanzilReferenceSource $source): int
    {
        $this->info('Mengunduh teks dan metadata resmi Tanzil…');
        $text = Http::timeout(45)->retry(2, 500)->get(self::TEXT_URL)->throw()->body();
        $metadataXml = Http::timeout(45)->retry(2, 500)->get(self::METADATA_URL)->throw()->body();
        $this->verifyHash('teks', $text, self::TEXT_SHA256);
        $this->verifyHash('metadata', $metadataXml, self::METADATA_SHA256);

        $texts = $source->parseText($text);
        $metadata = $source->parseMetadata($metadataXml);
        $this->validateCompleteSource($texts, $metadata);

        $existing = DB::table('quran_datasets')->where('checksum', self::TEXT_SHA256)->first();
        if ($existing) {
            if ($this->option('activate')) {
                $this->activate($existing->id);
            }
            $this->info('Tanzil Uthmani v1.1 sudah terpasang.');

            return self::SUCCESS;
        }

        $this->info('Memasang 6.236 ayat dan indeks kata…');
        DB::transaction(function () use ($texts, $metadata): void {
            $datasetId = (string) Str::ulid();
            $editionId = (string) Str::ulid();
            $now = now();
            DB::table('quran_datasets')->insert([
                'id' => $datasetId,
                'source_name' => 'Tanzil Project',
                'source_url' => 'https://tanzil.net/download/',
                'version' => '1.1',
                'riwayah' => 'Hafs ‘an ‘Asim · Uthmani Unicode',
                'license_metadata' => 'Tanzil Quran Text © 2007–2021 Tanzil Project · CC BY 3.0 · salinan verbatim, tidak boleh diubah · https://tanzil.net/docs/Text_License',
                'checksum' => self::TEXT_SHA256,
                'validation_status' => $this->option('activate') ? 'active' : 'validated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('mushaf_editions')->insert([
                'id' => $editionId,
                'dataset_id' => $datasetId,
                'name' => 'Tanzil Uthmani v1.1 · tampilan ayat',
                'version' => '1.1',
                'status' => $this->option('activate') ? 'active' : 'validated',
                'checksum' => hash('sha256', self::TEXT_SHA256.self::METADATA_SHA256),
                'license_metadata' => 'CC BY 3.0; atribusi dan tautan Tanzil wajib dipertahankan. Bukan reproduksi layout cetak per halaman.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ayahIds = [];
            $orderIds = [];
            $globalOrder = 0;
            $wordRows = [];
            foreach ($metadata['surahs'] as $surah) {
                $surahId = (string) Str::ulid();
                DB::table('surahs')->insert(['id' => $surahId, 'dataset_id' => $datasetId, 'number' => $surah['number'], 'name_local' => $surah['name'], 'ayah_count' => $surah['ayah_count']]);
                $ayahRows = [];
                for ($ayah = 1; $ayah <= $surah['ayah_count']; $ayah++) {
                    $key = $surah['number'].':'.$ayah;
                    $ayahId = (string) Str::ulid();
                    $globalOrder++;
                    $ayahIds[$key] = $ayahId;
                    $orderIds[$globalOrder] = $ayahId;
                    $ayahRows[] = ['id' => $ayahId, 'dataset_id' => $datasetId, 'surah_id' => $surahId, 'number' => $ayah, 'global_order' => $globalOrder, 'text_uthmani' => $texts[$key]];
                    foreach (preg_split('/\s+/u', trim($texts[$key])) ?: [] as $index => $word) {
                        $wordRows[] = ['id' => (string) Str::ulid(), 'edition_id' => $editionId, 'ayah_id' => $ayahId, 'position' => $index + 1, 'source_word_key' => $key.':'.($index + 1), 'text_or_glyph' => $word, 'token_kind' => 'word'];
                    }
                }
                DB::table('ayahs')->insert($ayahRows);
                foreach (array_chunk($wordRows, 750) as $chunk) {
                    DB::table('edition_words')->insert($chunk);
                }
                $wordRows = [];
            }
            foreach ($metadata['juz'] as $index => $juz) {
                $startKey = $juz['surah'].':'.$juz['ayah'];
                $next = $metadata['juz'][$index + 1] ?? null;
                $nextOrder = $next ? $this->globalOrder($metadata['surahs'], $next['surah'], $next['ayah']) : 6237;
                DB::table('juz_ranges')->insert([
                    'id' => (string) Str::ulid(), 'dataset_id' => $datasetId, 'juz_number' => $juz['number'],
                    'start_ayah_id' => $ayahIds[$startKey], 'end_ayah_id' => $orderIds[$nextOrder - 1],
                ]);
            }
            if ($this->option('activate')) {
                $this->activate($datasetId, $editionId);
            }
        }, 3);

        $this->info('Tanzil Uthmani v1.1 berhasil dipasang'.($this->option('activate') ? ' dan diaktifkan.' : '.'));
        $this->line('Teks: '.self::TEXT_SHA256);
        $this->line('Metadata: '.self::METADATA_SHA256);

        return self::SUCCESS;
    }

    private function verifyHash(string $label, string $contents, string $expected): void
    {
        $actual = hash('sha256', $contents);
        if (! hash_equals($expected, $actual)) {
            throw new RuntimeException("Checksum $label Tanzil berubah; impor dihentikan. Diperoleh: $actual");
        }
    }

    private function validateCompleteSource(array $texts, array $metadata): void
    {
        if (count($texts) !== 6236 || count($metadata['surahs']) !== 114 || count($metadata['juz']) !== 30 || count($metadata['pages']) !== 604) {
            throw new RuntimeException('Struktur Tanzil tidak lengkap; diperlukan 6.236 ayat, 114 surah, 30 juz, dan 604 halaman metadata.');
        }
        foreach ($metadata['surahs'] as $surah) {
            for ($ayah = 1; $ayah <= $surah['ayah_count']; $ayah++) {
                if (! isset($texts[$surah['number'].':'.$ayah])) {
                    throw new RuntimeException("Ayat {$surah['number']}:$ayah tidak tersedia.");
                }
            }
        }
    }

    private function globalOrder(array $surahs, int $surahNumber, int $ayahNumber): int
    {
        $order = $ayahNumber;
        foreach ($surahs as $surah) {
            if ($surah['number'] >= $surahNumber) {
                break;
            }
            $order += $surah['ayah_count'];
        }

        return $order;
    }

    private function activate(string $datasetId, ?string $editionId = null): void
    {
        $editionId ??= DB::table('mushaf_editions')->where('dataset_id', $datasetId)->value('id');
        DB::table('mushaf_editions')->where('status', 'active')->where('id', '!=', $editionId)->update(['status' => 'retired', 'updated_at' => now()]);
        DB::table('quran_datasets')->where('validation_status', 'active')->where('id', '!=', $datasetId)->update(['validation_status' => 'retired', 'updated_at' => now()]);
        DB::table('quran_datasets')->where('id', $datasetId)->update(['validation_status' => 'active', 'updated_at' => now()]);
        DB::table('mushaf_editions')->where('id', $editionId)->update(['status' => 'active', 'updated_at' => now()]);
    }
}
