export type WordAnchor = `${number}:${number}:${number}`;
export type AyahAnchor = `${number}:${number}`;

export type ReferenceWord = {
    key: WordAnchor;
    ayah: AyahAnchor;
    label: string;
    tokenKind: 'reading_word' | 'marker';
};

export type ReferenceLine = {
    number: number;
    type: 'surah_name' | 'basmallah' | 'ayah';
    surah?: number;
    words: ReferenceWord[];
};

export type ReferencePage = {
    number: number;
    juz: number;
    lines: ReferenceLine[];
};

export type ReferenceAnchor = { kind: 'ayah'; ayah: AyahAnchor } | {
    kind: 'word'; ayah: AyahAnchor; word: WordAnchor;
};

export function validateReferencePages(pages: ReferencePage[]): string[] {
    const issues: string[] = [];
    const pageNumbers = new Set<number>();
    const wordKeys = new Set<string>();
    const nextWordPosition = new Map<AyahAnchor, number>();

    for (const page of pages) {
        if (!Number.isInteger(page.number) || page.number < 1 || page.number > 604 || pageNumbers.has(page.number)) {
            issues.push(`Nomor halaman invalid/duplikat: ${page.number}`);
        }
        pageNumbers.add(page.number);
        if (!Number.isInteger(page.juz) || page.juz < 1 || page.juz > 30) issues.push(`Juz invalid: ${page.number}`);
        const lineNumbers = new Set<number>();
        for (const line of page.lines) {
            if (!Number.isInteger(line.number) || line.number < 1 || lineNumbers.has(line.number)) {
                issues.push(`Baris invalid/duplikat: ${page.number}:${line.number}`);
            }
            lineNumbers.add(line.number);
            if (line.type === 'ayah' && line.words.length === 0) issues.push(`Baris ayat kosong: ${page.number}:${line.number}`);
            if (line.type !== 'ayah' && line.words.length > 0) issues.push(`Baris dekorasi berisi kata: ${page.number}:${line.number}`);
            if (line.type === 'surah_name' && (!Number.isInteger(line.surah) || line.surah! < 1 || line.surah! > 114)) {
                issues.push(`Kepala surah invalid: ${page.number}:${line.number}`);
            }
            for (const word of line.words) {
                const match = /^(\d+):(\d+):(\d+)$/.exec(word.key);
                if (!match || `${match[1]}:${match[2]}` !== word.ayah || Number(match[1]) < 1 || Number(match[1]) > 114 || Number(match[2]) < 1 || Number(match[3]) < 1) {
                    issues.push(`Jangkar kata tidak cocok dengan ayat: ${word.key}`);
                } else if (!wordKeys.has(word.key)) {
                    const expected = nextWordPosition.get(word.ayah) ?? 1;
                    if (Number(match[3]) !== expected) issues.push(`Urutan kata terputus: ${word.key}; seharusnya posisi ${expected}`);
                    nextWordPosition.set(word.ayah, Number(match[3]) + 1);
                }
                if (wordKeys.has(word.key)) issues.push(`Jangkar kata duplikat: ${word.key}`);
                wordKeys.add(word.key);
                if (!word.label.trim()) issues.push(`Label kata kosong: ${word.key}`);
            }
        }
    }
    return issues;
}

export function wordsForAyah(page: ReferencePage, ayah: AyahAnchor): ReferenceWord[] {
    return page.lines.flatMap((line) => line.words).filter((word) => word.ayah === ayah);
}

export function parseAnchor(value: string | null, pages: ReferencePage[]): ReferenceAnchor | null {
    if (!value) return null;
    const [kind, key] = value.split('|');
    const words = pages.flatMap((page) => page.lines.flatMap((line) => line.words));
    if (kind === 'word') {
        const word = words.find((item) => item.key === key && item.tokenKind === 'reading_word');
        return word ? { kind: 'word', ayah: word.ayah, word: word.key } : null;
    }
    if (kind === 'ayah' && words.some((word) => word.ayah === key)) return { kind: 'ayah', ayah: key as AyahAnchor };
    return null;
}

export function anchorKey(anchor: ReferenceAnchor | null): string | null {
    if (!anchor) return null;
    return anchor.kind === 'word' ? `word|${anchor.word}` : `ayah|${anchor.ayah}`;
}
