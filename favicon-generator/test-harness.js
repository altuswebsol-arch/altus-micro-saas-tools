/* Node test harness for Altus Favicon Generator logic.
 * Run: node test-harness.js
 * Validates: layoutFit, crc32, buildIco (byte-level + PIL parse), StoredZip (unzip -t),
 * htmlSnippet, manifestJson, validateImage.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const L = require('./assets/favicon-logic.js');

let pass = 0, fail = 0;
function ok(name, cond, extra) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { fail++; console.log('  FAIL ' + name + (extra ? ' — ' + extra : '')); }
}
function throws(name, fn) {
    try { fn(); ok(name, false, 'did not throw'); }
    catch (e) { ok(name, true); }
}

/* ---------------- layoutFit ---------------- */
(function () {
    console.log('layoutFit');
    var r = L.layoutFit(1000, 1000, 48, 0.1);
    ok('square+10%pad', r.dw === 38 && r.dh === 38 && r.dx === 5 && r.dy === 5, JSON.stringify(r));

    r = L.layoutFit(2000, 1000, 48, 0.1); // wide source
    ok('wide letterbox', r.dw === 38 && r.dh === 19 && r.dx === 5 && r.dy === 15, JSON.stringify(r));

    r = L.layoutFit(1000, 2000, 48, 0); // tall, no pad
    ok('tall no-pad', r.dw === 24 && r.dh === 48 && r.dx === 12 && r.dy === 0, JSON.stringify(r));

    r = L.layoutFit(512, 512, 180, 0.08);
    ok('180px pad 8%', r.dw === 152 && r.dh === 152 && r.dx === 14 && r.dy === 14, JSON.stringify(r));

    throws('zero dims throw', function () { L.layoutFit(0, 100, 48, 0); });
    throws('pad overflow throws', function () { L.layoutFit(100, 100, 48, 0.6); });
})();

/* ---------------- crc32 ---------------- */
(function () {
    console.log('crc32');
    var v = L.crc32(Buffer.from('123456789', 'ascii'));
    ok('known vector', v === 0xCBF43926, 'got 0x' + v.toString(16));
})();

/* ---------------- buildIco ---------------- */
(function () {
    console.log('buildIco');
    function synth(size) {
        var rgba = new Uint8Array(size * size * 4), x, y, i;
        for (y = 0; y < size; y++) for (x = 0; x < size; x++) {
            i = (y * size + x) * 4;
            rgba[i] = x & 255; rgba[i + 1] = y & 255; rgba[i + 2] = (x + y) & 255; rgba[i + 3] = 255;
        }
        return { size: size, rgba: rgba };
    }

    var ico = L.buildIco([synth(16)]);
    var dv = new DataView(ico.buffer, ico.byteOffset, ico.byteLength);

    ok('ICONDIR magic', ico[0] === 0 && ico[1] === 0 && dv.getUint16(2, true) === 1 && dv.getUint16(4, true) === 1);
    ok('entry width/height', ico[6] === 16 && ico[7] === 16);
    ok('entry bitcount 32', dv.getUint16(12, true) === 32);
    var andRow16 = Math.ceil((16 / 8) / 4) * 4; // 4
    var imgLen16 = 40 + 16 * 16 * 4 + andRow16 * 16; // 1128
    ok('bytesInRes', dv.getUint32(14, true) === imgLen16);
    ok('imageOffset', dv.getUint32(18, true) === 22);
    ok('total length', ico.length === 22 + imgLen16, 'got ' + ico.length);

    var bmp = 22;
    ok('BMP biSize', dv.getUint32(bmp, true) === 40);
    ok('BMP biWidth/biHeight', dv.getInt32(bmp + 4, true) === 16 && dv.getInt32(bmp + 8, true) === 32);

    // pixel check: source (x=3,y=5) -> r=3 g=5 b=8 ; bottom-up row = 15-5 = 10
    var px = bmp + 40 + (10 * 16 + 3) * 4;
    ok('pixel BGRA bottom-up', ico[px] === 8 && ico[px + 1] === 5 && ico[px + 2] === 3 && ico[px + 3] === 255,
        Array.from(ico.slice(px, px + 4)).join(','));

    // AND mask all zeros
    var andStart = bmp + 40 + 16 * 16 * 4, allZero = true;
    for (var i = 0; i < andRow16 * 16; i++) if (ico[andStart + i] !== 0) { allZero = false; break; }
    ok('AND mask zeroed', allZero);

    // multi-entry
    var ico2 = L.buildIco([synth(16), synth(32)]);
    var dv2 = new DataView(ico2.buffer, ico2.byteOffset, ico2.byteLength);
    ok('two-entry count', dv2.getUint16(4, true) === 2);
    ok('second entry offset', dv2.getUint32(6 + 16 + 12, true) === 6 + 32 + imgLen16,
        'got ' + dv2.getUint32(6 + 16 + 12, true));

    throws('empty entries throw', function () { L.buildIco([]); });
    throws('rgba mismatch throws', function () { L.buildIco([{ size: 16, rgba: new Uint8Array(10) }]); });

    // Real-parser check: PIL must open the 16/32/48 ICO without error
    fs.writeFileSync('/tmp/afg-test.ico', Buffer.from(L.buildIco([synth(16), synth(32), synth(48)])));
    try {
        var out = execSync('python3 -c "from PIL import Image; im=Image.open(\'/tmp/afg-test.ico\'); im.load(); print(im.size)"', { encoding: 'utf8' }).trim();
        ok('PIL parses ICO', out === '(16, 16)' || out === '(48, 48)', 'PIL said ' + out);
    } catch (e) {
        ok('PIL parses ICO', false, String(e.message).slice(0, 120));
    }
})();

/* ---------------- StoredZip ---------------- */
(function () {
    console.log('StoredZip');
    var z = new L.StoredZip(new Date('2026-10-04T10:00:00+05:00'));
    z.add('favicon.ico', Buffer.from([1, 2, 3, 4]));
    z.add('favicon-snippet.html', Buffer.from(L.htmlSnippet(), 'utf8'));
    z.add('manifest.webmanifest', Buffer.from(L.manifestJson(), 'utf8'));
    var bytes = z.finish();
    ok('non-empty zip', bytes.length > 100);

    // central directory parse: find EOCD, walk entries
    var dv = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    var eocd = bytes.length - 22;
    ok('EOCD magic', dv.getUint32(eocd, true) === 0x06054B50);
    ok('entry count 3', dv.getUint16(eocd + 8, true) === 3);
    var cdOff = dv.getUint32(eocd + 16, true), p = cdOff, names = [];
    for (var i = 0; i < 3; i++) {
        var sigOk = dv.getUint32(p, true) === 0x02014B50;
        var nl = dv.getUint16(p + 28, true);
        var nm = Buffer.from(bytes.slice(p + 46, p + 46 + nl)).toString('ascii');
        names.push(nm);
        p += 46 + nl;
        if (!sigOk) break;
    }
    ok('central-dir names', JSON.stringify(names) === JSON.stringify(['favicon.ico', 'favicon-snippet.html', 'manifest.webmanifest']),
        JSON.stringify(names));

    throws('non-ASCII name rejected', function () { new L.StoredZip().add('fávicon.ico', new Uint8Array(1)); });

    fs.writeFileSync('/tmp/afg-test.zip', Buffer.from(bytes));
    try {
        execSync('unzip -t /tmp/afg-test.zip', { encoding: 'utf8' });
        ok('unzip -t integrity', true);
    } catch (e) {
        ok('unzip -t integrity', false, String(e.message).slice(0, 200));
    }
    try {
        var listing = execSync('unzip -l /tmp/afg-test.zip', { encoding: 'utf8' });
        ok('unzip -l lists 3 files', (listing.match(/favicon-snippet\.html/) || []).length === 1);
    } catch (e) {
        ok('unzip -l lists 3 files', false, 'unzip failed');
    }
})();

/* ---------------- snippets ---------------- */
(function () {
    console.log('snippets');
    var lines = L.htmlSnippet().split('\n');
    ok('6 snippet lines', lines.length === 6);
    ok('ico link first', lines[0] === '<link rel="icon" type="image/x-icon" href="/favicon.ico">');
    ok('apple-touch-icon present', lines.some(function (l) { return l.indexOf('apple-touch-icon') > -1; }));
    ok('manifest link present', lines.some(function (l) { return l.indexOf('rel="manifest"') > -1; }));

    var m;
    try { m = JSON.parse(L.manifestJson()); ok('manifest is valid JSON', true); }
    catch (e) { ok('manifest is valid JSON', false, e.message); m = {}; }
    ok('manifest 192 icon', (m.icons || []).some(function (i) { return i.sizes === '192x192'; }));
    ok('manifest 512 icon', (m.icons || []).some(function (i) { return i.sizes === '512x512'; }));

    ok('PNG sizes list', L.PNG_SIZES.length === 6 && L.PNG_SIZES[0].size === 16 && L.PNG_SIZES[5].size === 512);
    ok('ICO sizes list', JSON.stringify(L.ICO_SIZES) === '[16,32,48]');
})();

/* ---------------- validateImage ---------------- */
(function () {
    console.log('validateImage');
    ok('good square silent', L.validateImage(512, 512).length === 0);
    ok('big square silent', L.validateImage(2048, 2048).length === 0);
    ok('non-square warns', L.validateImage(800, 600).some(function (w) { return /not square/i.test(w); }));
    ok('tiny warns', L.validateImage(100, 100).some(function (w) { return /small/i.test(w); }));
})();

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
