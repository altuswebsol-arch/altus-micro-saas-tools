/* Altus Favicon Generator — pure logic module.
 * Shared by the browser UI (generator.js) and the Node test harness.
 * No DOM, no canvas, no dependencies. Works in Node and the browser.
 */
(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
    } else {
        root.AFGLogic = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Sizes we ship                                                      */
    /* ------------------------------------------------------------------ */
    var PNG_SIZES = [
        { size: 16,  file: 'favicon-16x16.png' },
        { size: 32,  file: 'favicon-32x32.png' },
        { size: 48,  file: 'favicon-48x48.png' },
        { size: 180, file: 'apple-touch-icon.png' },
        { size: 192, file: 'android-chrome-192x192.png' },
        { size: 512, file: 'android-chrome-512x512.png' }
    ];
    var ICO_SIZES = [16, 32, 48];

    /* ------------------------------------------------------------------ */
    /* layoutFit: contain-fit a source image into a square canvas with      */
    /* padding. Returns {dw, dh, dx, dy} for ctx.drawImage semantics.      */
    /* ------------------------------------------------------------------ */
    function layoutFit(srcW, srcH, size, padRatio) {
        if (!(srcW > 0 && srcH > 0 && size > 0)) {
            throw new Error('layoutFit: bad dimensions');
        }
        var pad = Math.round(size * (padRatio || 0));
        var box = size - pad * 2;
        if (box <= 0) throw new Error('layoutFit: padding exceeds canvas');
        var scale = box / Math.max(srcW, srcH);
        var dw = Math.round(srcW * scale);
        var dh = Math.round(srcH * scale);
        return { dw: dw, dh: dh, dx: Math.round((size - dw) / 2), dy: Math.round((size - dh) / 2) };
    }

    /* ------------------------------------------------------------------ */
    /* validateImage: honest warnings about the uploaded source.            */
    /* ------------------------------------------------------------------ */
    function validateImage(w, h) {
        var warnings = [];
        if (w !== h) {
            warnings.push('Your image is not square (' + w + '×' + h + '). It will be letterboxed into a square — a square source usually looks better.');
        }
        var longest = Math.max(w, h);
        if (longest < 260) {
            warnings.push('Your image is small (' + longest + 'px). Favicons will still be made, but a source of 512×512 or larger gives noticeably crisper results.');
        }
        return warnings;
    }

    /* ------------------------------------------------------------------ */
    /* CRC-32 (needed by the ZIP writer)                                   */
    /* ------------------------------------------------------------------ */
    var CRC_TABLE = (function () {
        var t = new Uint32Array(256), c, k, n;
        for (n = 0; n < 256; n++) {
            c = n;
            for (k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            t[n] = c >>> 0;
        }
        return t;
    })();
    function crc32(bytes) {
        var crc = 0xFFFFFFFF, i;
        for (i = 0; i < bytes.length; i++) crc = CRC_TABLE[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    /* ------------------------------------------------------------------ */
    /* Little-endian writers                                               */
    /* ------------------------------------------------------------------ */
    function u16(view, off, v) { view.setUint16(off, v, true); }
    function u32(view, off, v) { view.setUint32(off, v >>> 0, true); }

    function dosDateTime(d) {
        d = d || new Date();
        var time = ((d.getHours() & 31) << 11) | ((d.getMinutes() & 63) << 5) | ((Math.floor(d.getSeconds() / 2)) & 31);
        var date = (((d.getFullYear() - 1980) & 127) << 9) | (((d.getMonth() + 1) & 15) << 5) | (d.getDate() & 31);
        return { time: time, date: date };
    }

    /* ------------------------------------------------------------------ */
    /* buildIco: entries = [{ size, rgba }] where rgba is RGBA bytes        */
    /* (top-down, left-to-right) of length size*size*4. Returns Uint8Array.  */
    /* BMP-based ICO, 32bpp + (zeroed) AND mask per entry.                  */
    /* ------------------------------------------------------------------ */
    function buildIco(entries) {
        if (!entries || !entries.length) throw new Error('buildIco: no entries');
        entries.forEach(function (e) {
            if (!(e.size > 0 && e.size <= 256)) throw new Error('buildIco: bad size ' + e.size);
            if (!e.rgba || e.rgba.length !== e.size * e.size * 4) {
                throw new Error('buildIco: rgba length mismatch for size ' + e.size);
            }
        });

        var n = entries.length;
        var headerLen = 6 + 16 * n;
        var imgLens = entries.map(function (e) {
            var s = e.size;
            var andRow = Math.ceil((s / 8) / 4) * 4;      // AND-mask rows are DWORD-aligned
            return 40 + s * s * 4 + andRow * s;            // BMP header + XOR + AND
        });
        var total = headerLen;
        imgLens.forEach(function (l) { total += l; });

        var buf = new Uint8Array(total);
        var view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

        // ICONDIR
        u16(view, 0, 0);        // reserved
        u16(view, 2, 1);        // type: ICO
        u16(view, 4, n);        // count

        var off = headerLen;
        entries.forEach(function (e, i) {
            var s = e.size, p = 6 + 16 * i;
            buf[p]     = s === 256 ? 0 : s;   // width  (0 = 256)
            buf[p + 1] = s === 256 ? 0 : s;   // height (0 = 256)
            buf[p + 2] = 0;                   // color count
            buf[p + 3] = 0;                   // reserved
            u16(view, p + 4, 1);              // planes
            u16(view, p + 6, 32);             // bit count
            u32(view, p + 8, imgLens[i]);     // bytes in resource
            u32(view, p + 12, off);           // image offset

            // BITMAPINFOHEADER
            var q = off;
            u32(view, q, 40);                 // biSize
            u32(view, q + 4, s);              // biWidth
            u32(view, q + 8, s * 2);          // biHeight (XOR + AND)
            u16(view, q + 12, 1);             // biPlanes
            u16(view, q + 14, 32);            // biBitCount
            u32(view, q + 16, 0);             // biCompression = BI_RGB
            u32(view, q + 20, s * s * 4);     // biSizeImage
            u32(view, q + 24, 0);             // biXPelsPerMeter
            u32(view, q + 28, 0);             // biYPelsPerMeter
            u32(view, q + 32, 0);             // biClrUsed
            u32(view, q + 36, 0);             // biClrImportant

            // XOR mask: bottom-up rows, BGRA order
            var px = q + 40, row, col, si, di;
            for (row = 0; row < s; row++) {
                for (col = 0; col < s; col++) {
                    si = ((s - 1 - row) * s + col) * 4;   // source: top-down → bottom-up
                    di = px + (row * s + col) * 4;
                    buf[di]     = e.rgba[si + 2];         // B
                    buf[di + 1] = e.rgba[si + 1];         // G
                    buf[di + 2] = e.rgba[si];             // R
                    buf[di + 3] = e.rgba[si + 3];         // A
                }
            }
            // AND mask: all zeros (fully opaque to the mask; alpha lives in XOR)
            var andStart = px + s * s * 4;
            var andRow = Math.ceil((s / 8) / 4) * 4;
            for (row = 0; row < andRow * s; row++) buf[andStart + row] = 0;

            off += imgLens[i];
        });

        return buf;
    }

    /* ------------------------------------------------------------------ */
    /* StoredZip: minimal uncompressed ZIP writer (no dependencies).        */
    /* ------------------------------------------------------------------ */
    function StoredZip(date) {
        this.files = [];
        this.dos = dosDateTime(date);
    }
    StoredZip.prototype.add = function (name, bytes) {
        if (typeof name !== 'string' || !name.length) throw new Error('StoredZip: bad name');
        var b = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
        // names must be ASCII for our simple writer
        for (var i = 0; i < name.length; i++) {
            if (name.charCodeAt(i) > 127) throw new Error('StoredZip: non-ASCII name');
        }
        this.files.push({ name: name, bytes: b, crc: crc32(b) });
    };
    StoredZip.prototype.finish = function () {
        var nameBytes = this.files.map(function (f) {
            var n = new Uint8Array(f.name.length);
            for (var i = 0; i < f.name.length; i++) n[i] = f.name.charCodeAt(i);
            return n;
        });

        var localSize = 0, cdSize = 0, i, f, nb;
        for (i = 0; i < this.files.length; i++) {
            localSize += 30 + nameBytes[i].length + this.files[i].bytes.length;
            cdSize += 46 + nameBytes[i].length;
        }
        var total = localSize + cdSize + 22;
        var buf = new Uint8Array(total);
        var view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
        var off = 0, offsets = [];

        for (i = 0; i < this.files.length; i++) {
            f = this.files[i]; nb = nameBytes[i];
            offsets.push(off);
            // local file header
            u32(view, off, 0x04034B50); u16(view, off + 4, 20); u16(view, off + 6, 0);
            u16(view, off + 8, 0); // method: stored
            u16(view, off + 10, this.dos.time); u16(view, off + 12, this.dos.date);
            u32(view, off + 14, f.crc); u32(view, off + 18, f.bytes.length); u32(view, off + 22, f.bytes.length);
            u16(view, off + 26, nb.length); u16(view, off + 28, 0); // extra len
            buf.set(nb, off + 30);
            buf.set(f.bytes, off + 30 + nb.length);
            off += 30 + nb.length + f.bytes.length;
        }

        var cdOff = off;
        for (i = 0; i < this.files.length; i++) {
            f = this.files[i]; nb = nameBytes[i];
            u32(view, off, 0x02014B50); u16(view, off + 4, 20); u16(view, off + 6, 20);
            u16(view, off + 8, 0); u16(view, off + 10, 0);
            u16(view, off + 12, this.dos.time); u16(view, off + 14, this.dos.date);
            u32(view, off + 16, f.crc); u32(view, off + 20, f.bytes.length); u32(view, off + 24, f.bytes.length);
            u16(view, off + 28, nb.length); u16(view, off + 30, 0); u16(view, off + 32, 0);
            u16(view, off + 34, 0); u16(view, off + 36, 0); u32(view, off + 38, 0);
            u32(view, off + 42, offsets[i]);
            buf.set(nb, off + 46);
            off += 46 + nb.length;
        }

        // end of central directory
        u32(view, off, 0x06054B50); u16(view, off + 4, 0); u16(view, off + 6, 0);
        u16(view, off + 8, this.files.length); u16(view, off + 10, this.files.length);
        u32(view, off + 12, cdSize); u32(view, off + 16, cdOff); u16(view, off + 20, 0);

        return buf;
    };

    /* ------------------------------------------------------------------ */
    /* Snippets                                                             */
    /* ------------------------------------------------------------------ */
    function htmlSnippet() {
        return [
            '<link rel="icon" type="image/x-icon" href="/favicon.ico">',
            '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">',
            '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">',
            '<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">',
            '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">',
            '<link rel="manifest" href="/manifest.webmanifest">'
        ].join('\n');
    }

    function manifestJson() {
        return JSON.stringify({
            name: 'Your Site Name',
            short_name: 'Site',
            description: 'Web app manifest — replace the names with your own.',
            icons: [
                { src: '/android-chrome-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                { src: '/android-chrome-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' }
            ],
            theme_color: '#ffffff',
            background_color: '#ffffff',
            display: 'standalone'
        }, null, 2) + '\n';
    }

    return {
        PNG_SIZES: PNG_SIZES,
        ICO_SIZES: ICO_SIZES,
        layoutFit: layoutFit,
        validateImage: validateImage,
        crc32: crc32,
        buildIco: buildIco,
        StoredZip: StoredZip,
        htmlSnippet: htmlSnippet,
        manifestJson: manifestJson
    };
}));
