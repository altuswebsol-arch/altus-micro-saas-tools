/* Altus Review Link Generator — browser UI.
 * Requires: assets/review-links.js (AltusRLG), assets/qrcode.min.js (QRCode).
 */
(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }

    function onReady(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function showError(id, msg) {
        var el = $(id);
        if (!el) return;
        el.textContent = msg;
        el.hidden = !msg;
    }

    function copyText(text, done) {
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { /* noop */ }
            document.body.removeChild(ta);
            done(ok);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { done(true); }, function () { fallback(); });
        } else {
            fallback();
        }
    }

    var state = { reviewLink: '', mapsLink: '', businessName: '', qrDark: '#111111', qrLight: '#ffffff' };
    var qr = null;

    function renderQR() {
        var holder = $('arlg-qr');
        if (!holder || typeof QRCode === 'undefined') return;
        holder.innerHTML = '';
        qr = new QRCode(holder, {
            text: state.reviewLink,
            width: 220,
            height: 220,
            colorDark: state.qrDark,
            colorLight: state.qrLight,
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    function qrDataURL() {
        // qrcodejs renders a canvas (plus a hidden img); prefer the canvas pixels.
        var holder = $('arlg-qr');
        if (!holder) return '';
        var canvas = holder.querySelector('canvas');
        if (canvas && canvas.toDataURL) {
            try { return canvas.toDataURL('image/png'); } catch (e) { /* noop */ }
        }
        var img = holder.querySelector('img');
        if (img && img.src && img.src.indexOf('data:image') === 0) return img.src;
        return '';
    }

    function downloadQR() {
        var url = qrDataURL();
        if (!url) {
            alert('The QR code is not ready yet — please wait a moment and try again.');
            return;
        }
        var a = document.createElement('a');
        a.href = url;
        a.download = 'google-review-qr.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function doGenerate() {
        var raw = $('arlg-input').value;
        var name = $('arlg-bizname').value;
        var res = AltusRLG.resolveInput(raw, name);
        showError('arlg-e-input', '');
        if (!res.ok) {
            showError('arlg-e-input', res.error);
            return;
        }
        state.reviewLink = res.reviewLink;
        state.mapsLink = res.mapsLink || '';
        state.businessName = AltusRLG.clean(name);

        $('arlg-s1').hidden = true;
        $('arlg-result').hidden = false;
        $('arlg-send-done').hidden = true;
        $('arlg-send-form').hidden = false;

        var linkOut = $('arlg-link-out');
        linkOut.textContent = state.reviewLink;
        linkOut.href = state.reviewLink;

        var mapsWrap = $('arlg-maps-wrap');
        if (state.mapsLink) {
            mapsWrap.hidden = false;
            var mOut = $('arlg-maps-out');
            mOut.textContent = state.mapsLink;
            mOut.href = state.mapsLink;
        } else {
            mapsWrap.hidden = true;
        }

        var note = $('arlg-mode-note');
        if (res.note) { note.textContent = res.note; note.hidden = false; }
        else { note.hidden = true; }

        renderQR();
        $('arlg-result').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function doStartOver() {
        $('arlg-result').hidden = true;
        $('arlg-s1').hidden = false;
        $('arlg-s1').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function sendPack(e) {
        e.preventDefault();
        showError('arlg-e-send-name', '');
        showError('arlg-e-send-email', '');

        var name = $('arlg-send-name').value.trim();
        var email = $('arlg-send-email').value.trim();
        var bad = false;
        if (name.length < 2) { showError('arlg-e-send-name', 'Please enter your name.'); bad = true; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
            showError('arlg-e-send-email', 'Please enter a valid email address.'); bad = true;
        }
        if (bad) return;

        var btn = $('arlg-send-btn');
        btn.disabled = true;
        btn.textContent = 'Sending…';

        var payload = {
            action: 'arlg_send_pack',
            nonce: (window.ARLG && window.ARLG.nonce) || '',
            name: name,
            email: email,
            business_name: state.businessName,
            review_link: state.reviewLink,
            qr_png: qrDataURL()
        };

        fetch((window.ARLG && window.ARLG.ajax) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(payload).toString()
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.success) {
                $('arlg-send-form').hidden = true;
                $('arlg-send-done').hidden = false;
            } else {
                var msg = (json && json.data && json.data.message) || 'Something went wrong — please try again.';
                showError('arlg-e-send-email', msg);
            }
        }).catch(function () {
            showError('arlg-e-send-email', 'Could not reach the server — check your connection and try again.');
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = 'Email me my review pack';
        });
    }

    onReady(function () {
        if (!$('arlg-root') || typeof AltusRLG === 'undefined') return;

        $('arlg-generate').addEventListener('click', doGenerate);
        $('arlg-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); doGenerate(); }
        });
        $('arlg-start-over').addEventListener('click', doStartOver);

        $('arlg-copy-link').addEventListener('click', function () {
            var btn = this;
            copyText(state.reviewLink, function (ok) {
                btn.textContent = ok ? 'Copied ✓' : 'Copy failed — select the link above';
                setTimeout(function () { btn.textContent = 'Copy review link'; }, 2500);
            });
        });

        var mapsCopy = $('arlg-copy-maps');
        if (mapsCopy) {
            mapsCopy.addEventListener('click', function () {
                var btn = this;
                copyText(state.mapsLink, function (ok) {
                    btn.textContent = ok ? 'Copied ✓' : 'Copy failed';
                    setTimeout(function () { btn.textContent = 'Copy maps link'; }, 2500);
                });
            });
        }

        $('arlg-dl-qr').addEventListener('click', downloadQR);

        var dark = $('arlg-qr-dark'), light = $('arlg-qr-light');
        function recolor() {
            state.qrDark = dark.value;
            state.qrLight = light.value;
            if (!$('arlg-result').hidden) renderQR();
        }
        if (dark && light) {
            dark.addEventListener('input', recolor);
            light.addEventListener('input', recolor);
            $('arlg-qr-reset').addEventListener('click', function () {
                dark.value = '#111111'; light.value = '#ffffff'; recolor();
            });
        }

        $('arlg-send-form').addEventListener('submit', sendPack);

        // "How do I find my Place ID?" toggle
        var howT = $('arlg-how-toggle'), howB = $('arlg-how-body');
        if (howT && howB) {
            howT.addEventListener('click', function () {
                var open = howB.hidden;
                howB.hidden = !open;
                howT.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    });
})();
