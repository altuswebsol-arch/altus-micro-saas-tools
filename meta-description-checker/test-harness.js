#!/usr/bin/env node
/* Altus Meta Description Checker — test harness.
 * 1) Unit-tests the pure helpers (meta-logic.js).
 * 2) Syntax-checks generator.js (UI wiring) without a DOM.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const BASE = __dirname;
let pass = 0, fail = 0;
function ok(name, cond, extra) {
    if (cond) { pass++; /* console.log('  PASS  ' + name); */ }
    else { fail++; console.log('  FAIL  ' + name + (extra ? ' — ' + extra : '')); }
}

const M = require(path.join(BASE, 'assets', 'meta-logic.js'));
console.log('logic helpers:');

// --- estimateWidth ---
ok('width: empty', M.estimateWidth('') === 0);
ok('width: "i" narrow', M.estimateWidth('i') < M.estimateWidth('W'));
ok('width: "W" wide', M.estimateWidth('W', 14) === Math.round(944 * 14 / 1000 * 0.85), M.estimateWidth('W', 14));
ok('width: scales with font size', M.estimateWidth('abc', 20) > M.estimateWidth('abc', 14));
ok('width: unknown char uses default', M.estimateWidth('→', 14) === Math.round(556 * 14 / 1000 * 0.85), M.estimateWidth('→', 14));
ok('width: collapses whitespace', M.estimateWidth('a  b') === M.estimateWidth('a b'));
ok('width: typical 155-char desc near 920px', (function () {
    var s = 'Affordable web design for UK small businesses. We build fast, beautiful websites that win customers every single day. Book your free consultation now!';
    var w = M.estimateWidth(s, 14);
    return s.length >= 150 && s.length <= 160 && w > 750 && w < 1100;
})());

// --- truncateToWidth ---
var t = M.truncateToWidth('short text', 920);
ok('truncate: fits unchanged', t === 'short text', t);
var longWords = 'word '.repeat(80).trim();
t = M.truncateToWidth(longWords, 920);
ok('truncate: ends with ellipsis', /\.\.\.$/.test(t), t.slice(-20));
ok('truncate: cuts at word boundary', (function () {
    var core = t.replace(/\.\.\.$/, '');
    return longWords.indexOf(core) === 0 &&
        (longWords.length === core.length || longWords.charAt(core.length) === ' ');
})(), t.slice(-30));
ok('truncate: within budget', M.estimateWidth(t.replace(/\.\.\.$/, '')) <= 920, M.estimateWidth(t));
ok('fitsWidth true', M.fitsWidth('hi', 920) === true);
ok('fitsWidth false', M.fitsWidth('x'.repeat(300), 920) === false);

// --- allCapsWords ---
ok('caps: finds SHOUTING', JSON.stringify(M.allCapsWords('This is AMAZING stuff')) === '["AMAZING"]', JSON.stringify(M.allCapsWords('This is AMAZING stuff')));
ok('caps: ignores UK', M.allCapsWords('Serving the UK daily').length === 0);
ok('caps: ignores short words', M.allCapsWords('A BIG win').length === 0);
ok('caps: ignores normal', M.allCapsWords('Normal sentence here').length === 0);

// --- containsWord / ctaPresent ---
ok('containsWord: whole word', M.containsWord('Book your table today', 'book') === true);
ok('containsWord: no substring match', M.containsWord('Facebook page', 'book') === false);
ok('ctaPresent: finds book', M.ctaPresent('Book your free quote today') === 'book');
ok('ctaPresent: none', M.ctaPresent('We are a nice company.') === null);

// --- analyzeDescription ---
var r = M.analyzeDescription('', '', '');
ok('desc: empty fails', r.checks[0].status === 'fail' && r.checks.length === 1);

r = M.analyzeDescription('Short.', '', '');
ok('desc: short warns', r.checks.some(c => c.id === 'length' && c.status === 'warn'));
ok('desc: short chars', r.chars === 6, r.chars);

var good = 'Affordable web design for UK small businesses. We build fast, beautiful websites that win customers. Book your free consultation today.';
r = M.analyzeDescription(good, 'Affordable web design for UK small businesses', 'web design');
ok('desc: good length pass', r.checks.some(c => c.id === 'length' && c.status === 'pass'));
ok('desc: fits pixels pass', r.checks.some(c => c.id === 'pixels' && c.status === 'pass'));
ok('desc: keyword pass', r.checks.some(c => c.id === 'keyword' && c.status === 'pass'));
ok('desc: unique pass', r.checks.some(c => c.id === 'unique' && c.status === 'pass'));
ok('desc: cta pass', r.checks.some(c => c.id === 'cta' && c.status === 'pass'));
ok('desc: ending pass', r.checks.some(c => c.id === 'ending' && c.status === 'pass'));
ok('desc: not truncated', r.truncated === false);

var long = 'x'.repeat(300);
r = M.analyzeDescription(long, '', '');
ok('desc: long warns length', r.checks.some(c => c.id === 'length' && c.status === 'warn'));
ok('desc: long truncated', r.truncated === true);
ok('desc: long pixels warn', r.checks.some(c => c.id === 'pixels' && c.status === 'warn'));

r = M.analyzeDescription('Same title here', 'Same title here', '');
ok('desc: identical to title fails', r.checks.some(c => c.id === 'unique' && c.status === 'fail'));

r = M.analyzeDescription('This is an AMAZING offer for everyone reading today.', '', '');
ok('desc: caps fail', r.checks.some(c => c.id === 'caps' && c.status === 'fail'));

r = M.analyzeDescription('A decent description without a closing mark and no action words at all here', '', '');
ok('desc: no cta warns', r.checks.some(c => c.id === 'cta' && c.status === 'warn'));
ok('desc: no ending warns', r.checks.some(c => c.id === 'ending' && c.status === 'warn'));

r = M.analyzeDescription('Great web design for small firms. We build fast sites.', 'Web Design Services', 'plumber');
ok('desc: missing keyword warns', r.checks.some(c => c.id === 'keyword' && c.status === 'warn'));

r = M.analyzeDescription('We build websites for small businesses across the United Kingdom and we take real pride in every single project we deliver for web design clients.', '', 'web design');
ok('desc: buried keyword warns', r.checks.some(c => c.id === 'keyword' && c.status === 'warn'));

// --- analyzeTitle ---
var rt = M.analyzeTitle('', '');
ok('title: empty fails', rt.checks[0].status === 'fail');

rt = M.analyzeTitle('Web Design for Small Businesses | Altus', 'web design');
ok('title: good length pass', rt.checks.some(c => c.id === 'length' && c.status === 'pass'));
ok('title: keyword pass', rt.checks.some(c => c.id === 'keyword' && c.status === 'pass'));

rt = M.analyzeTitle('Hi', '');
ok('title: short warns', rt.checks.some(c => c.id === 'length' && c.status === 'warn'));

rt = M.analyzeTitle('This is an extremely long page title that keeps going and going and will definitely be cut off by Google', '');
ok('title: long warns', rt.checks.some(c => c.id === 'length' && c.status === 'warn'));

rt = M.analyzeTitle('BEST DEALS EVER HERE', '');
ok('title: caps fail', rt.checks.some(c => c.id === 'caps' && c.status === 'fail'));

// --- scoring ---
var dGood = M.analyzeDescription(good, 'Affordable web design for UK small businesses', 'web design').checks;
var tGood = M.analyzeTitle('Affordable Web Design for UK Small Businesses | Altus', 'web design').checks;
var s = M.scoreAll(dGood, tGood);
ok('score: good input high', s >= 85, s);

var dBad = M.analyzeDescription('SAME', 'SAME', '').checks;
var tBad = M.analyzeTitle('SAME', '').checks;
var sBad = M.scoreAll(dBad, tBad);
ok('score: bad input low', sBad < 60, sBad);
ok('score: good beats bad', s > sBad);

ok('grade: 95 excellent', M.gradeFor(95).label === 'Excellent');
ok('grade: 70 good', M.gradeFor(70).label === 'Good');
ok('grade: 50 needs work', M.gradeFor(50).label === 'Needs work');
ok('grade: 10 poor', M.gradeFor(10).label === 'Poor');

// --- buildReport ---
var rep = M.buildReport('https://example.com/', 'T', 'D', 'kw', 90, 'Excellent', dGood, tGood);
ok('report: contains score', rep.indexOf('90/100') !== -1);
ok('report: contains tool url', rep.indexOf('meta-description-checker') !== -1);
ok('report: no keyword line when empty', M.buildReport('', 'T', 'D', '', 10, 'Poor', dBad, tBad).indexOf('Keyword checked') === -1);

// --- 2. generator.js syntax -------------------------------------------
try {
    const src = fs.readFileSync(path.join(BASE, 'assets', 'generator.js'), 'utf8');
    new vm.Script(src, { filename: 'generator.js' });
    ok('generator.js syntax', true);
} catch (e) {
    ok('generator.js syntax', false, e.message);
}

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
