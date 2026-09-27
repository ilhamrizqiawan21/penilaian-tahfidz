<?php

namespace Tests\Unit;

use App\Services\TanzilReferenceSource;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TanzilReferenceSourceTest extends TestCase
{
    #[Test]
    public function it_parses_verbatim_uthmani_rows_and_metadata(): void
    {
        $source = new TanzilReferenceSource;
        $rows = $source->parseText("1|1|بِسْمِ ٱللَّهِ\n1|2|ٱلْحَمْدُ لِلَّهِ\n# license\n");
        $metadata = $source->parseMetadata('<?xml version="1.0"?><quran><suras><sura index="1" ayas="2" name="الفاتحة" /></suras><juzs><juz index="1" sura="1" aya="1" /></juzs><pages><page index="1" sura="1" aya="1" /></pages></quran>');

        $this->assertSame('بِسْمِ ٱللَّهِ', $rows['1:1']);
        $this->assertSame('ٱلْحَمْدُ لِلَّهِ', $rows['1:2']);
        $this->assertSame(['number' => 1, 'ayah_count' => 2, 'name' => 'الفاتحة'], $metadata['surahs'][0]);
        $this->assertSame(['number' => 1, 'surah' => 1, 'ayah' => 1], $metadata['juz'][0]);
        $this->assertCount(1, $metadata['pages']);
    }

    #[Test]
    public function it_rejects_duplicate_or_malformed_quran_rows(): void
    {
        $source = new TanzilReferenceSource;

        $this->expectException(InvalidArgumentException::class);
        $source->parseText("1|1|نص\n1|1|نص آخر\n");
    }
}
