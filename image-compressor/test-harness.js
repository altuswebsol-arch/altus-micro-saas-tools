#!/usr/bin/env node
/* Altus Image Compressor — test harness.
 * 1) Unit-tests the pure helpers (compressor-logic.js).
 * 2) Syntax-checks generator.js (UI wiring) without a DOM.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const BASE = __dirname;
let pass = 0, fail = 0;
function ok(name, cond, extra) {
    if (cond) { pass++; console.log('  PASS  ' + name); }
    else { fail++; console.log('  FAIL  ' + name + (extra ? ' — ' + extra : '')); }
}

// --- 1. logic helpers -------------------------------------------------
const C = require(path.join(BASE, 'assets', 'compressor-logic.js'));
console.log('logic helpers:');

ok('formatBytes: bytes', C.formatBytes(512) === '512 B', C.formatBytes(512));
ok('formatBytes: KB', C.formatBytes(2048) === '2 KB', C.formatBytes(2048));
ok('formatBytes: KB decimal', C.formatBytes(35000) === '34.2 KB', C.formatBytes(35000));
ok('formatBytes: MB', C.formatBytes(2516582) === '2.4 MB', C.formatBytes(2516582));
ok('formatBytes: zero', C.formatBytes(0) === '0 B');
ok('formatBytes: garbage', C.formatBytes(NaN) === '0 B');

ok('extensionFor jpeg', C.extensionFor('image/jpeg') === 'jpg');
ok('extensionFor png', C.extensionFor('image/png') === 'png');
ok('extensionFor webp', C.extensionFor('image/webp') === 'webp');
ok('outputFilename', C.outputFilename('holiday-photo.PNG', 'image/jpeg') === 'holiday-photo-compressed.jpg', C.outputFilename('holiday-photo.PNG', 'image/jpeg'));
ok('outputFilename strips dirs', C.outputFilename('photo', 'image/webp') === 'photo-compressed.webp');

ok('validateFile: jpeg ok', C.validateFile({ type: 'image/jpeg', size: 1000 }).ok === true);
ok('validateFile: png ok', C.validateFile({ type: 'image/png', size: 1000 }).ok === true);
ok('validateFile: webp ok', C.validateFile({ type: 'image/webp', size: 1000 }).ok === true);
ok('validateFile: gif rejected', C.validateFile({ type: 'image/gif', size: 1000 }).ok === false);
ok('validateFile: pdf rejected', C.validateFile({ type: 'application/pdf', size: 1000 }).ok === false);
ok('validateFile: too big rejected', C.validateFile({ type: 'image/jpeg', size: 26 * 1024 * 1024 }).ok === false);
ok('validateFile: exactly 25MB ok', C.validateFile({ type: 'image/jpeg', size: 25 * 1024 * 1024 }).ok === true);
ok('validateFile: empty rejected', C.validateFile({ type: 'image/jpeg', size: 0 }).ok === false);
ok('validateFile: missing rejected', C.validateFile(null).ok === false);

let d = C.scaleDimensions(4000, 3000, 1600);
ok('scaleDimensions 4000x3000 @1600', d.width === 1600 && d.height === 1200, JSON.stringify(d));
d = C.scaleDimensions(800, 600, 1600);
ok('scaleDimensions: no upscale', d.width === 800 && d.height === 600, JSON.stringify(d));
d = C.scaleDimensions(800, 600, 0);
ok('scaleDimensions: maxW 0 keeps original', d.width === 800 && d.height === 600);
d = C.scaleDimensions(2000, 1000, 500);
ok('scaleDimensions 2000x1000 @500', d.width === 500 && d.height === 250, JSON.stringify(d));
d = C.scaleDimensions(0, 0, 100);
ok('scaleDimensions: zero input safe', d.width === 0 && d.height === 0);
d = C.scaleDimensions(1001, 999, 1000);
ok('scaleDimensions: odd rounding min height 1', d.width === 1000 && d.height >= 1);

ok('savingsPercent: half', C.savingsPercent(1000, 500) === 50);
ok('savingsPercent: zero', C.savingsPercent(1000, 1000) === 0);
ok('savingsPercent: grew', C.savingsPercent(1000, 1200) === -20);
ok('savingsPercent: original 0 safe', C.savingsPercent(0, 500) === 0);

ok('qualityApplies jpeg', C.qualityApplies('image/jpeg') === true);
ok('qualityApplies webp', C.qualityApplies('image/webp') === true);
ok('qualityApplies png false', C.qualityApplies('image/png') === false);

ok('formatAdvice png out warns', /lossless/i.test(C.formatAdvice('image/jpeg', 'image/png')));
ok('formatAdvice png->webp', /biggest savings/i.test(C.formatAdvice('image/png', 'image/webp')));
ok('formatAdvice webp praises', /sweet spot/i.test(C.formatAdvice('image/jpeg', 'image/webp')));
ok('formatAdvice jpeg empty', C.formatAdvice('image/jpeg', 'image/jpeg') === '');

ok('mayLoseTransparency png->jpeg', C.mayLoseTransparency('image/png', 'image/jpeg') === true);
ok('mayLoseTransparency jpeg->jpeg false', C.mayLoseTransparency('image/jpeg', 'image/jpeg') === false);
ok('mayLoseTransparency png->webp false', C.mayLoseTransparency('image/png', 'image/webp') === false);

ok('emailSizeOk under cap', C.emailSizeOk(1024) === true);
ok('emailSizeOk over cap', C.emailSizeOk(3 * 1024 * 1024) === false);

ok('clampQuality clamps low', C.clampQuality(3) === 10);
ok('clampQuality clamps high', C.clampQuality(150) === 100);
ok('clampQuality rounds', C.clampQuality(82.6) === 83);
ok('clampQuality garbage default', C.clampQuality('abc') === 80);

ok('verdictLabel huge', C.verdictLabel(80) === 'Huge saving');
ok('verdictLabel great', C.verdictLabel(50) === 'Great saving');
ok('verdictLabel good', C.verdictLabel(20) === 'Good saving');
ok('verdictLabel small', C.verdictLabel(5) === 'Small saving');
ok('verdictLabel zero', C.verdictLabel(0) === 'No change');
ok('verdictLabel grew', C.verdictLabel(-5) === 'Slightly larger');

// --- 2. generator.js syntax --------------------------------------------
console.log('generator.js:');
try {
    const src = fs.readFileSync(path.join(BASE, 'assets', 'generator.js'), 'utf8');
    new vm.Script(src); // parses without executing (IIFE not invoked by parse)
    ok('syntax clean', true);
} catch (e) {
    ok('syntax clean', false, e.message);
}

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
