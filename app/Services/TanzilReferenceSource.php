<?php

namespace App\Services;

use InvalidArgumentException;

class TanzilReferenceSource
{
    /** @return array<string, string> */
    public function parseText(string $contents): array
    {
        $rows = [];
        foreach (preg_split('/\R/u', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('|', $line, 3);
            if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || trim($parts[2]) === '') {
                throw new InvalidArgumentException('Baris teks Tanzil tidak valid.');
            }
            $key = ((int) $parts[0]).':'.((int) $parts[1]);
            if (isset($rows[$key])) {
                throw new InvalidArgumentException("Ayat duplikat pada sumber Tanzil: $key.");
            }
            $rows[$key] = $parts[2];
        }
        if ($rows === []) {
            throw new InvalidArgumentException('Teks Tanzil kosong.');
        }

        return $rows;
    }

    /** @return array{surahs: array<int, array{number: int, ayah_count: int, name: string}>, juz: array<int, array{number: int, surah: int, ayah: int}>, pages: array<int, array{number: int, surah: int, ayah: int}>} */
    public function parseMetadata(string $contents): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false || ! isset($xml->suras, $xml->juzs, $xml->pages)) {
            throw new InvalidArgumentException('Metadata Tanzil tidak valid.');
        }

        $surahs = [];
        foreach ($xml->suras->sura as $node) {
            $surahs[] = ['number' => (int) $node['index'], 'ayah_count' => (int) $node['ayas'], 'name' => (string) $node['name']];
        }
        $partition = static function ($nodes): array {
            $result = [];
            foreach ($nodes as $node) {
                $result[] = ['number' => (int) $node['index'], 'surah' => (int) $node['sura'], 'ayah' => (int) $node['aya']];
            }

            return $result;
        };

        return ['surahs' => $surahs, 'juz' => $partition($xml->juzs->juz), 'pages' => $partition($xml->pages->page)];
    }
}
