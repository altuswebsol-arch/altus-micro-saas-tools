/* Altus Favicon Generator — browser UI.
 * Requires: assets/favicon-logic.js (window.AFGLogic).
 * All image work stays client-side. The only server contact is the
 * optional "email me the files" form (admin-ajax, sends real email).
 */
(function () {
    'use strict';
    var L = window.AFGLogic;
    if (!L) { return; }

    var $ = function (id) { return document.getElementById(id); };

    var state = {
        img: null, srcW: 0, srcH: 0, objUrl: null,
        bg: 'transparent', pad: 0.08,
        canvases: {}, icoBytes: null, zipBytes: null
    };

    var BG_COLORS = {
        transparent: null,
        white: '#ffffff',
        navy: '#0f2033',
        blue: '#10b3fe'
    };

    /* ---------------- helpers ---------------- */
    function show(el) { el.hidden = false; }
    function hide(el) { el.hidden = true; }
    function err(id, msg) {
        var e = $(id);
        if (!msg) { e.hidden = true; e.textContent = ''; return; }
        e.hidden = false; e.textContent = msg;
    }
    function setProgress(pct) { $('afcg-progress-bar').style.width = pct + '%'; }
    function downloadBlob(blob, filename) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 4000);
    }
    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (b) { b ? resolve(b) : reject(new Error('encode failed')); }, 'image/png');
        });
    }
    function blobToU8(blob) {
        return blob.arrayBuffer().then(function (ab) { return new Uint8Array(ab); });
    }

    /* ---------------- step 1: upload ---------------- */
    var dz = $('afcg-drop'), fi = $('afcg-file');

    ['dragover', 'dragenter'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('afcg-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('afcg-dragover'); });
    });
    dz.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
    dz.addEventListener('click', function () { fi.click(); });
    dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fi.click(); } });
    fi.addEventListener('change', function () {
        if (fi.files && fi.files[0]) handleFile(fi.files[0]);
        fi.value = '';
    });

    function handleFile(file) {
        err('afcg-e-file');
        if (!/^image\//.test(file.type)) {
            err('afcg-e-file', 'That is not an image file. Please choose a PNG, JPG or similar image.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            err('afcg-e-file', 'That image is over 10 MB. Please use a smaller file.');
            return;
        }
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            if (state.objUrl) URL.revokeObjectURL(state.objUrl);
            state.img = img; state.objUrl = url;
            state.srcW = img.naturalWidth; state.srcH = img.naturalHeight;
            var warn = $('afcg-warnings'); warn.innerHTML = '';
            L.validateImage(state.srcW, state.srcH).forEach(function (w) {
                var p = document.createElement('p');
                p.className = 'afcg-warn'; p.textContent = w;
                warn.appendChild(p);
            });
            renderPreview();
            hide($('afcg-s1')); show($('afcg-s2')); setProgress(66);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            err('afcg-e-file', 'Could not read that image. Please try a different file.');
        };
        img.src = url;
    }

    /* ---------------- step 2: options + live preview ---------------- */
    document.querySelectorAll('input[name="afcg-bg"]').forEach(function (r) {
        r.addEventListener('change', function () {
            state.bg = document.querySelector('input[name="afcg-bg"]:checked').value;
            syncBgCards(); renderPreview();
        });
    });
    document.querySelectorAll('input[name="afcg-pad"]').forEach(function (r) {
        r.addEventListener('change', function () {
            state.pad = parseFloat(document.querySelector('input[name="afcg-pad"]:checked').value);
            syncPadCards(); renderPreview();
        });
    });
    function syncBgCards() {
        document.querySelectorAll('[data-afcg-bg-card]').forEach(function (c) {
            c.classList.toggle('checked', c.getAttribute('data-afcg-bg-card') === state.bg);
        });
    }
    function syncPadCards() {
        document.querySelectorAll('[data-afcg-pad-card]').forEach(function (c) {
            c.classList.toggle('checked', parseFloat(c.getAttribute('data-afcg-pad-card')) === state.pad);
        });
    }

    function renderCanvas(size) {
        var c = document.createElement('canvas');
        c.width = size; c.height = size;
        var ctx = c.getContext('2d');
        var bg = BG_COLORS[state.bg];
        if (bg) { ctx.fillStyle = bg; ctx.fillRect(0, 0, size, size); }
        else { ctx.clearRect(0, 0, size, size); }
        ctx.imageSmoothingQuality = 'high';
        var lay = L.layoutFit(state.srcW, state.srcH, size, state.pad);
        ctx.drawImage(state.img, lay.dx, lay.dy, lay.dw, lay.dh);
        return c;
    }

    function renderPreview() {
        if (!state.img) return;
        // size previews
        var grid = $('afcg-size-previews'); grid.innerHTML = '';
        [180, 48, 32].forEach(function (s) {
            var c = renderCanvas(s);
            var wrap = document.createElement('div');
            wrap.className = 'afcg-size-prev';
            var box = document.createElement('div');
            box.className = 'afcg-size-box' + (BG_COLORS[state.bg] ? '' : ' afcg-checker');
            var show = document.createElement('canvas');
            show.width = 64; show.height = 64;
            var sctx = show.getContext('2d');
            sctx.imageSmoothingQuality = 'high';
            sctx.drawImage(c, 0, 0, 64, 64);
            box.appendChild(show);
            var lab = document.createElement('span');
            lab.textContent = s + '×' + s;
            wrap.appendChild(box); wrap.appendChild(lab);
            grid.appendChild(wrap);
        });
        // browser-tab mock at real 16px
        var c16 = renderCanvas(16);
        var tabImg = $('afcg-tab-icon');
        tabImg.width = 16; tabImg.height = 16;
        tabImg.getContext('2d').drawImage(c16, 0, 0);
    }

    $('afcg-generate').addEventListener('click', function () {
        generate();
        hide($('afcg-s2')); show($('afcg-s3')); setProgress(100);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    $('afcg-back-s1').addEventListener('click', function () {
        hide($('afcg-s2')); show($('afcg-s1')); setProgress(33);
    });

    /* ---------------- step 3: results ---------------- */
    function generate() {
        state.canvases = {};
        L.PNG_SIZES.forEach(function (s) { state.canvases[s.size] = renderCanvas(s.size); });

        // ICO from 16/32/48
        var entries = L.ICO_SIZES.map(function (s) {
            var data = state.canvases[s].getContext('2d').getImageData(0, 0, s, s).data;
            return { size: s, rgba: new Uint8Array(data) };
        });
        state.icoBytes = L.buildIco(entries);
        state.zipBytes = null;

        // preview the final 16px in the results list
        var list = $('afcg-file-list'); list.innerHTML = '';
        var rows = [
            { label: 'favicon.ico — for browsers (16, 32, 48px inside)', dl: function () {
                downloadBlob(new Blob([state.icoBytes], { type: 'image/x-icon' }), 'favicon.ico');
            } }
        ];
        L.PNG_SIZES.forEach(function (s) {
            rows.push({ label: s.file + ' — ' + pngUse(s.size), dl: (function (cv, name) {
                return function () {
                    canvasToBlob(cv).then(function (b) { downloadBlob(b, name); });
                };
            })(state.canvases[s.size], s.file) });
        });
        rows.forEach(function (r) {
            var div = document.createElement('div');
            div.className = 'afcg-file-row';
            var sp = document.createElement('span'); sp.textContent = r.label;
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'afcg-btn afcg-btn-secondary afcg-btn-sm';
            btn.textContent = 'Download';
            btn.addEventListener('click', r.dl);
            div.appendChild(sp); div.appendChild(btn);
            list.appendChild(div);
        });
    }

    function pngUse(size) {
        if (size === 180) return 'Apple touch icon (iPhone/iPad home screen)';
        if (size === 192 || size === 512) return 'Android / PWA manifest';
        return 'modern browsers';
    }

    $('afcg-dl-zip').addEventListener('click', function () {
        var btn = $('afcg-dl-zip');
        btn.disabled = true; btn.textContent = 'Preparing…';
        buildZip().then(function (zipBytes) {
            state.zipBytes = zipBytes;
            downloadBlob(new Blob([zipBytes], { type: 'application/zip' }), 'favicon-package.zip');
            btn.disabled = false; btn.textContent = 'Download everything (.zip)';
        }).catch(function () {
            btn.disabled = false; btn.textContent = 'Download everything (.zip)';
            err('afcg-e-zip', 'Something went wrong building the zip. The individual downloads above still work.');
        });
    });

    function buildZip() {
        var pngBlobs = L.PNG_SIZES.map(function (s) { return canvasToBlob(state.canvases[s.size]); });
        return Promise.all(pngBlobs).then(function (blobs) {
            return Promise.all(blobs.map(blobToU8)).then(function (u8s) {
                var z = new L.StoredZip(new Date());
                z.add('favicon.ico', state.icoBytes);
                L.PNG_SIZES.forEach(function (s, i) { z.add(s.file, u8s[i]); });
                z.add('manifest.webmanifest', new TextEncoder().encode(L.manifestJson()));
                z.add('favicon-snippet.html', new TextEncoder().encode(L.htmlSnippet()));
                return z.finish();
            });
        });
    }

    /* ---------------- snippets ---------------- */
    $('afcg-snippet-html').textContent = L.htmlSnippet();
    $('afcg-snippet-manifest').textContent = L.manifestJson();

    $('afcg-copy-html').addEventListener('click', function () { copyText(L.htmlSnippet(), 'afcg-copy-html'); });
    $('afcg-copy-manifest').addEventListener('click', function () { copyText(L.manifestJson(), 'afcg-copy-manifest'); });
    $('afcg-dl-manifest').addEventListener('click', function () {
        downloadBlob(new Blob([L.manifestJson()], { type: 'application/manifest+json' }), 'manifest.webmanifest');
    });

    function copyText(text, btnId) {
        var btn = $(btnId);
        function done() {
            var old = btn.textContent;
            btn.textContent = 'Copied ✓';
            setTimeout(function () { btn.textContent = old; }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
        } else { fallbackCopy(text); done(); }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
        ta.remove();
    }

    /* ---------------- email me the files ---------------- */
    $('afcg-send-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var name = $('afcg-send-name').value.trim();
        var email = $('afcg-send-email').value.trim();
        var okName = name.length >= 2;
        var okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        err('afcg-e-send-name', okName ? '' : 'Please enter your name.');
        err('afcg-e-send-email', okEmail ? '' : 'Please enter a valid email address.');
        if (!okName || !okEmail) return;

        var btn = $('afcg-send-btn');
        btn.disabled = true; btn.textContent = 'Sending…';
        buildZip().then(function (zipBytes) {
            if (zipBytes.length > 2 * 1024 * 1024) {
                throw new Error('too-big');
            }
            var b64 = '';
            var CH = 0x8000;
            for (var i = 0; i < zipBytes.length; i += CH) {
                b64 += String.fromCharCode.apply(null, zipBytes.subarray(i, i + CH));
            }
            return fetch(AFCG.ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'action=afcg_send_files&nonce=' + encodeURIComponent(AFCG.nonce) +
                      '&name=' + encodeURIComponent(name) +
                      '&email=' + encodeURIComponent(email) +
                      '&zip=' + encodeURIComponent(btoa(b64))
            }).then(function (r) { return r.json(); });
        }).then(function (res) {
            if (res && res.success) {
                hide($('afcg-send-form')); show($('afcg-send-done'));
            } else {
                throw new Error((res && res.data) || 'send failed');
            }
        }).catch(function (er) {
            btn.disabled = false; btn.textContent = 'Email me the files';
            err('afcg-e-send-email', er.message === 'too-big'
                ? 'The package is too large to email — please use the download buttons above instead.'
                : 'Sorry, that did not send. Please use the download buttons above instead.');
        });
    });

    $('afcg-start-over').addEventListener('click', function () {
        hide($('afcg-s3')); show($('afcg-s1')); setProgress(33);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    setProgress(33);
})();
