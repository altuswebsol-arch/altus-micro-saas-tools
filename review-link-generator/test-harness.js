#!/usr/bin/env node
/* Test harness for the Altus Review Link Generator link logic.
 * Run: node test-harness.js
 */
const RLG = require('./assets/review-links.js');

let pass = 0, fail = 0;
function check(name, cond) {
    if (cond) { pass++; console.log('PASS  ' + name); }
    else { fail++; console.log('FAIL  ' + name); }
}
function throws(name, fn) {
    try { fn(); fail++; console.log('FAIL  ' + name + ' (did not throw)'); }
    catch (e) { pass++; console.log('PASS  ' + name); }
}

// --- Place ID validation ---
check('valid real-format Place ID accepted',
    RLG.isValidPlaceId('ChIJN1t_tDeuEmsRUsoyG83frY4'));
check('Place ID with dash accepted',
    RLG.isValidPlaceId('ChIJ-abcDEF123456789012345'));
check('short token rejected', !RLG.isValidPlaceId('abc123'));
check('empty rejected', !RLG.isValidPlaceId(''));
check('whitespace-only rejected', !RLG.isValidPlaceId('   '));
check('business name rejected', !RLG.isValidPlaceId('Springfield Plumbing Ltd'));
check('URL rejected as Place ID', !RLG.isValidPlaceId('https://google.com/maps/foo'));
check('spaces inside rejected', !RLG.isValidPlaceId('ChIJ N1t tDeu'));

// --- extractPlaceId ---
check('extracts placeid from full writereview URL',
    RLG.extractPlaceId('https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4') === 'ChIJN1t_tDeuEmsRUsoyG83frY4');
check('plain ID passes through',
    RLG.extractPlaceId('ChIJN1t_tDeuEmsRUsoyG83frY4') === 'ChIJN1t_tDeuEmsRUsoyG83frY4');
check('trims whitespace', RLG.extractPlaceId('  ChIJN1t_tDeuEmsRUsoyG83frY4\n') === 'ChIJN1t_tDeuEmsRUsoyG83frY4');

// --- buildReviewLink ---
check('review link has Google writereview base',
    RLG.buildReviewLink('ChIJN1t_tDeuEmsRUsoyG83frY4') ===
    'https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4');
check('review link from full URL input',
    RLG.buildReviewLink('https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4') ===
    'https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4');
throws('review link throws on junk input', () => RLG.buildReviewLink('not a place id'));
throws('review link throws on empty', () => RLG.buildReviewLink(''));

// --- buildMapsLink ---
check('maps link contains query_place_id',
    RLG.buildMapsLink('ChIJN1t_tDeuEmsRUsoyG83frY4') ===
    'https://www.google.com/maps/search/?api=1&query_place_id=ChIJN1t_tDeuEmsRUsoyG83frY4');
check('maps link includes encoded business name',
    RLG.buildMapsLink('ChIJN1t_tDeuEmsRUsoyG83frY4', 'Springfield Plumbing Ltd') ===
    'https://www.google.com/maps/search/?api=1&query_place_id=ChIJN1t_tDeuEmsRUsoyG83frY4&query=Springfield%20Plumbing%20Ltd');
throws('maps link throws on junk', () => RLG.buildMapsLink('hello world'));

// --- parseGPage ---
check('g.page link normalised with /review',
    RLG.parseGPage('https://g.page/r/AbCdEfGhIjK/review') === 'https://g.page/r/AbCdEfGhIjK/review');
check('g.page link without /review gets it appended',
    RLG.parseGPage('https://g.page/r/AbCdEfGhIjK') === 'https://g.page/r/AbCdEfGhIjK/review');
check('g.page with www accepted',
    RLG.parseGPage('https://www.g.page/r/AbCdEfGhIjK/') === 'https://g.page/r/AbCdEfGhIjK/review');
check('non-g.page URL rejected', RLG.parseGPage('https://google.com/maps/abc') === null);
check('random text rejected', RLG.parseGPage('hello') === null);

// --- isHttpUrl ---
check('https URL accepted', RLG.isHttpUrl('https://search.google.com/local/writereview?placeid=abc123DEF456'));
check('http (not https) rejected', !RLG.isHttpUrl('http://example.com'));
check('javascript: rejected', !RLG.isHttpUrl('javascript:alert(1)'));
check('empty rejected', !RLG.isHttpUrl(''));

// --- resolveInput ---
let r = RLG.resolveInput('ChIJN1t_tDeuEmsRUsoyG83frY4', 'Test Bakery Ltd');
check('resolveInput placeid ok', r.ok && r.mode === 'placeid' &&
    r.reviewLink === 'https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4' &&
    r.mapsLink.indexOf('query=Test%20Bakery%20Ltd') !== -1);
r = RLG.resolveInput('https://g.page/r/AbCdEfGhIjK', '');
check('resolveInput g.page ok', r.ok && r.mode === 'gpage' &&
    r.reviewLink === 'https://g.page/r/AbCdEfGhIjK/review');
r = RLG.resolveInput('   ', '');
check('resolveInput empty not ok with message', !r.ok && r.error.length > 10);
r = RLG.resolveInput('Springfield Plumbing', '');
check('resolveInput junk not ok with guidance', !r.ok && r.error.indexOf('Place ID') !== -1);
r = RLG.resolveInput('https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4', '');
check('resolveInput accepts full URL input', r.ok && r.mode === 'placeid');

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
