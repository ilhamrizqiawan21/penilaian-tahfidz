import { type ReferencePage, type ReferenceWord, validateReferencePages } from './reference.ts';

// Data ini sintetis. Hanya nomor/jangkar untuk menguji UI; bukan ayat atau layout Madinah.
function word(key: `${number}:${number}:${number}`): ReferenceWord {
    return { key, ayah: key.split(':').slice(0, 2).join(':') as `${number}:${number}`, label: `Kata ${key}`, tokenKind: 'reading_word' };
}

export const fixturePages: ReferencePage[] = [
    { number: 1, juz: 1, lines: [
        { number: 1, type: 'surah_name', surah: 1, words: [] },
        { number: 2, type: 'ayah', words: [word('1:1:1'), word('1:1:2')] },
        { number: 3, type: 'ayah', words: [word('1:1:3'), word('1:2:1')] },
    ] },
    { number: 2, juz: 1, lines: [
        { number: 1, type: 'surah_name', surah: 2, words: [] },
        { number: 2, type: 'basmallah', words: [] },
        { number: 3, type: 'ayah', words: [word('2:1:1'), word('2:1:2')] },
    ] },
    { number: 302, juz: 15, lines: [
        { number: 1, type: 'surah_name', surah: 18, words: [] },
        { number: 2, type: 'ayah', words: [word('18:1:1'), word('18:1:2')] },
    ] },
    { number: 303, juz: 16, lines: [
        { number: 1, type: 'ayah', words: [word('18:1:3'), word('18:1:4')] },
        { number: 2, type: 'ayah', words: [word('18:2:1')] },
    ] },
    { number: 604, juz: 30, lines: [
        { number: 1, type: 'surah_name', surah: 114, words: [] },
        { number: 2, type: 'basmallah', words: [] },
        { number: 3, type: 'ayah', words: [word('114:1:1'), word('114:1:2')] },
    ] },
];

const fixtureIssues = validateReferencePages(fixturePages);
if (fixtureIssues.length > 0) throw new Error(`Fixture mushaf invalid: ${fixtureIssues.join('; ')}`);
