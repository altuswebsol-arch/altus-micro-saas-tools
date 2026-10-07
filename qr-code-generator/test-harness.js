#!/usr/bin/env node
/* Altus QR Code Generator — test harness.
 * 1) Unit-tests the pure content builders (qr-content.js).
 * 2) Smoke-tests the bundled QR engine (qrcode-generator 1.4.4): structural
 *    invariants (finder/timing patterns, version sizing, determinism) that a
 *    correct encoder must satisfy.
 * 3) Syntax-checks generator.js (UI wiring) without a DOM.
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

// --- 1. content builders -------------------------------------------------
const C = require(path.join(BASE, 'assets', 'qr-content.js'));
console.log('content builders:');

let r = C.buildPayload('url', { url: 'altuswebsolutions.com' });
ok('url auto-prefixes https://', r.payload === 'https://altuswebsolutions.com' && !r.error, JSON.stringify(r));
r = C.buildPayload('url', { url: 'https://example.com/page?a=1&b=2' });
ok('url passes through', r.payload === 'https://example.com/page?a=1&b=2' && !r.error);
r = C.buildPayload('url', { url: 'not a url at all!!!' });
ok('url rejects garbage', !!r.error);
r = C.buildPayload('url', { url: '' });
ok('url rejects empty', !!r.error);

r = C.buildPayload('text', { text: '  Hello Beeya  ' });
ok('text trims', r.payload === 'Hello Beeya' && !r.error, JSON.stringify(r));
r = C.buildPayload('text', { text: '   ' });
ok('text rejects blank', !!r.error);

r = C.buildPayload('email', { email: 'info@altuswebsolutions.com' });
ok('email builds mailto:', r.payload === 'mailto:info@altuswebsolutions.com' && !r.error);
r = C.buildPayload('email', { email: 'bad-email' });
ok('email rejects invalid', !!r.error);

r = C.buildPayload('phone', { phone: '+44 20 3488 6786' });
ok('phone strips spaces, builds tel:', r.payload === 'tel:+442034886786' && !r.error, JSON.stringify(r));
r = C.buildPayload('phone', { phone: 'abc' });
ok('phone rejects letters', !!r.error);

r = C.buildPayload('sms', { smsNumber: '+44 7700 900000', smsMessage: 'Hi! Book me in.' });
ok('sms builds with body', r.payload === 'sms:+447700900000?body=Hi!%20Book%20me%20in.' && !r.error, JSON.stringify(r));
r = C.buildPayload('sms', { smsNumber: '+447700900000', smsMessage: '' });
ok('sms builds without body', r.payload === 'sms:+447700900000' && !r.error);

r = C.buildPayload('wifi', { ssid: 'Cafe-Guest', password: 's3cr3t!', security: 'WPA', hidden: false });
ok('wifi builds WPA', r.payload === 'WIFI:T:WPA;S:Cafe-Guest;P:s3cr3t!;H:false;;' && !r.error, JSON.stringify(r));
r = C.buildPayload('wifi', { ssid: 'My;Net:Name', password: 'a\\b', security: 'WPA', hidden: true });
ok('wifi escapes specials', r.payload === 'WIFI:T:WPA;S:My\\;Net\\:Name;P:a\\\\b;H:true;;' && !r.error, JSON.stringify(r));
r = C.buildPayload('wifi', { ssid: 'OpenNet', password: '', security: 'nopass', hidden: false });
ok('wifi open network omits P:', r.payload === 'WIFI:T:nopass;S:OpenNet;H:false;;' && !r.error, JSON.stringify(r));
r = C.buildPayload('wifi', { ssid: '', password: 'x', security: 'WPA' });
ok('wifi rejects empty SSID', !!r.error);

ok('contrast black/white ~21', Math.abs(C.contrastRatio('#000000', '#ffffff') - 21) < 0.1,
    String(C.contrastRatio('#000000', '#ffffff')));
ok('contrast identical = 1', Math.abs(C.contrastRatio('#10b3fe', '#10b3fe') - 1) < 0.001);
ok('contrast navy/white readable', C.contrastRatio('#0f2033', '#ffffff') > 7);
ok('contrast similar colours low', C.contrastRatio('#f1f4f8', '#ffffff') < 2);

// --- 2. QR engine structural tests ---------------------------------------
console.log('qr engine:');
const engineSrc = fs.readFileSync(path.join(BASE, 'assets', 'vendor', 'qrcode.min.js'), 'utf8');
const qrcode = new Function(engineSrc + '\nreturn qrcode;')();

function make(data, ec) {
    const qr = qrcode(0, ec || 'M');
    qr.addData(data);
    qr.make();
    return qr;
}

// Finder pattern: 7x7 with dark border + 3x3 dark centre, at (r0,c0)
const FINDER = [
    '1111111',
    '1000001',
    '1011101',
    '1011101',
    '1011101',
    '1000001',
    '1111111'
];
function checkFinder(qr, r0, c0) {
    for (let r = 0; r < 7; r++)
        for (let c = 0; c < 7; c++)
            if (!!qr.isDark(r0 + r, c0 + c) !== (FINDER[r][c] === '1')) return false;
    return true;
}

let qr = make('https://altuswebsolutions.com/');
let mc = qr.getModuleCount();
ok('short URL -> version 1..4 (mc=' + mc + ')', mc >= 21 && mc <= 41 && (mc - 17) % 4 === 0);
ok('finder top-left', checkFinder(qr, 0, 0));
ok('finder top-right', checkFinder(qr, 0, mc - 7));
ok('finder bottom-left', checkFinder(qr, mc - 7, 0));
// separators: row 7 / col 7 around top-left finder must be light
let sepOk = true;
for (let i = 0; i < 8; i++) {
    if (qr.isDark(7, i) || qr.isDark(i, 7)) { sepOk = false; break; }
}
ok('separators light', sepOk);
// timing pattern: row 6 alternates, starting dark at col 8
let timOk = true;
for (let c = 8; c < mc - 8; c++) {
    if (!!qr.isDark(6, c) !== (c % 2 === 0)) { timOk = false; break; }
}
ok('timing pattern alternates', timOk);

// determinism
const qr2 = make('https://altuswebsolutions.com/');
let same = mc === qr2.getModuleCount();
for (let r = 0; same && r < mc; r++)
    for (let c = 0; c < mc; c++)
        if (!!qr.isDark(r, c) !== !!qr2.isDark(r, c)) { same = false; break; }
ok('encoding is deterministic', same);

// version grows with content
const big = make('https://altuswebsolutions.com/' + 'a'.repeat(300));
ok('longer content -> bigger version', big.getModuleCount() > mc,
    'small=' + mc + ' big=' + big.getModuleCount());

// all EC levels accepted
let ecOk = true;
for (const ec of ['L', 'M', 'Q', 'H']) {
    try { make('https://example.com/', ec); } catch (e) { ecOk = false; }
}
ok('EC levels L/M/Q/H accepted', ecOk);

// UTF-8 / byte mode
try {
    const u = make('https://example.com/café?x=1&y=2');
    ok('utf-8 URL encodes', u.getModuleCount() >= 21);
} catch (e) { ok('utf-8 URL encodes', false, String(e)); }

// wifi payload from our own builder encodes
try {
    const w = make(C.buildPayload('wifi', { ssid: 'Cafe', password: 'pw', security: 'WPA' }).payload);
    ok('wifi payload encodes', w.getModuleCount() >= 21);
} catch (e) { ok('wifi payload encodes', false, String(e)); }

// overflow: engine must throw so the UI can catch it
let threw = false;
try { make('x'.repeat(4000), 'H'); } catch (e) { threw = true; }
ok('oversize content throws (UI catches)', threw);

// dark module present somewhere (sanity: not all light)
let anyDark = false;
for (let r = 0; r < mc && !anyDark; r++)
    for (let c = 0; c < mc; c++) if (qr.isDark(r, c)) { anyDark = true; break; }
ok('modules contain dark cells', anyDark);

// --- 3. generator.js syntax ------------------------------------------------
console.log('ui wiring:');
try {
    new vm.Script(fs.readFileSync(path.join(BASE, 'assets', 'generator.js'), 'utf8'),
        { filename: 'generator.js' });
    ok('generator.js parses', true);
} catch (e) { ok('generator.js parses', false, String(e)); }
try {
    new vm.Script(fs.readFileSync(path.join(BASE, 'assets', 'qr-content.js'), 'utf8'),
        { filename: 'qr-content.js' });
    ok('qr-content.js parses', true);
} catch (e) { ok('qr-content.js parses', false, String(e)); }

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
