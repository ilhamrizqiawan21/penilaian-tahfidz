import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fixturePages } from '../../resources/js/Mushaf/fixture.ts';
import { anchorKey, parseAnchor, validateReferencePages, wordsForAyah } from '../../resources/js/Mushaf/reference.ts';

test('fixture includes start, middle, end and valid anchors', () => {
    assert.deepEqual(fixturePages.map((page) => page.number), [1, 2, 302, 303, 604]);
    assert.deepEqual(validateReferencePages(fixturePages), []);
    assert.deepEqual(wordsForAyah(fixturePages[2], '18:1').map((word) => word.key), ['18:1:1', '18:1:2']);
    assert.deepEqual(wordsForAyah(fixturePages[3], '18:1').map((word) => word.key), ['18:1:3', '18:1:4']);
    const anchor = parseAnchor('word|18:1:3', fixturePages);
    assert.deepEqual(anchor, { kind: 'word', ayah: '18:1', word: '18:1:3' });
    assert.equal(anchorKey(anchor), 'word|18:1:3');
    assert.equal(parseAnchor('word|18:1:99', fixturePages), null);
});

test('rejects repeated or discontinuous word identities across pages', () => {
    const pages = structuredClone(fixturePages);
    pages[3].lines[0].words[0].key = '18:1:2';
    assert.ok(validateReferencePages(pages).some((issue) => issue.includes('duplikat')));
    pages[3].lines[0].words[0].key = '18:1:5';
    assert.ok(validateReferencePages(pages).some((issue) => issue.includes('terputus')));
});
