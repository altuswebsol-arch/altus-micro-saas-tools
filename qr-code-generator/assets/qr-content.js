/* Altus QR Code Generator — content builders + helpers.
 * Pure functions, no DOM. UMD so the same file is unit-tested in Node
 * and loaded in the browser ahead of generator.js. */
(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
    } else {
        root.AQGContent = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    // Keep payloads comfortably inside QR capacity (version 40-L holds 2953
    // bytes in byte mode; we cap well below so the UI can stay friendly).
    var MAX_SAFE_CHARS = 2000;

    function trim(s) {
        return String(s == null ? '' : s).replace(/^\s+|\s+$/g, '');
    }

    function normalizeUrl(raw) {
        var u = trim(raw);
        if (!u) return { url: '', error: 'Enter a website address.' };
        if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(u)) u = 'https://' + u;
        if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/\S+\.\S+/.test(u)) {
            return { url: '', error: 'That does not look like a valid web address.' };
        }
        return { url: u, error: '' };
    }

    // Wi-Fi QR spec escaping: backslash-escape \ ; , : "
    function wifiEscape(s) {
        return String(s)
            .replace(/\\/g, '\\\\')
            .replace(/;/g, '\\;')
            .replace(/,/g, '\\,')
            .replace(/:/g, '\\:')
            .replace(/"/g, '\\"');
    }

    function cleanPhone(raw) {
        return trim(raw).replace(/[\s\-().]/g, '');
    }

    function validPhone(p) {
        return /^\+?[0-9]{6,15}$/.test(p);
    }

    function buildPayload(type, f) {
        f = f || {};
        switch (type) {
            case 'url': {
                var n = normalizeUrl(f.url);
                if (n.error) return { payload: '', error: n.error };
                return { payload: n.url, error: '' };
            }
            case 'text': {
                var t = trim(f.text);
                if (!t) return { payload: '', error: 'Type or paste some text first.' };
                return { payload: t, error: '' };
            }
            case 'email': {
                var e = trim(f.email);
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
                    return { payload: '', error: 'Enter a valid email address.' };
                }
                return { payload: 'mailto:' + e, error: '' };
            }
            case 'phone': {
                var p = cleanPhone(f.phone);
                if (!validPhone(p)) return { payload: '', error: 'Enter a valid phone number.' };
                return { payload: 'tel:' + p, error: '' };
            }
            case 'sms': {
                var sn = cleanPhone(f.smsNumber);
                if (!validPhone(sn)) return { payload: '', error: 'Enter a valid mobile number.' };
                var msg = trim(f.smsMessage);
                return { payload: 'sms:' + sn + (msg ? '?body=' + encodeURIComponent(msg) : ''), error: '' };
            }
            case 'wifi': {
                var ssid = trim(f.ssid);
                if (!ssid) return { payload: '', error: 'Enter the Wi-Fi network name (SSID).' };
                var sec = f.security || 'WPA';
                if (sec !== 'WPA' && sec !== 'WEP' && sec !== 'nopass') sec = 'WPA';
                var out = 'WIFI:T:' + sec + ';S:' + wifiEscape(ssid) + ';';
                if (sec !== 'nopass') out += 'P:' + wifiEscape(f.password || '') + ';';
                out += 'H:' + (f.hidden ? 'true' : 'false') + ';;';
                return { payload: out, error: '' };
            }
            default:
                return { payload: '', error: 'Choose what your QR code should contain.' };
        }
    }

    function hexToRgb(hex) {
        var h = String(hex).replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (!/^[0-9a-fA-F]{6}$/.test(h)) return null;
        return {
            r: parseInt(h.slice(0, 2), 16),
            g: parseInt(h.slice(2, 4), 16),
            b: parseInt(h.slice(4, 6), 16)
        };
    }

    function luminance(rgb) {
        function ch(v) {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * ch(rgb.r) + 0.7152 * ch(rgb.g) + 0.0722 * ch(rgb.b);
    }

    // WCAG relative contrast ratio, 1 (identical) to 21 (black on white).
    function contrastRatio(hexA, hexB) {
        var a = hexToRgb(hexA), b = hexToRgb(hexB);
        if (!a || !b) return 0;
        var l1 = luminance(a), l2 = luminance(b);
        if (l1 < l2) { var t = l1; l1 = l2; l2 = t; }
        return (l1 + 0.05) / (l2 + 0.05);
    }

    return {
        MAX_SAFE_CHARS: MAX_SAFE_CHARS,
        trim: trim,
        normalizeUrl: normalizeUrl,
        wifiEscape: wifiEscape,
        buildPayload: buildPayload,
        contrastRatio: contrastRatio
    };
}));
