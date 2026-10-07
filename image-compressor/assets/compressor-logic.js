/* Altus Image Compressor — pure helpers + compression math.
 * Pure functions, no DOM. UMD so the same file is unit-tested in Node
 * and loaded in the browser ahead of generator.js. */
(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
    } else {
        root.AICLogic = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_INPUT_BYTES = 25 * 1024 * 1024;   // 25 MB client-side cap
    var MAX_EMAIL_BYTES = 2 * 1024 * 1024;    // 2 MB attachment cap
    var LOSSY_TYPES = ['image/jpeg', 'image/webp'];

    function trim(s) {
        return String(s == null ? '' : s).replace(/^\s+|\s+$/g, '');
    }

    // Friendly file size: 512 B, 34.2 KB, 2.4 MB
    function formatBytes(bytes) {
        bytes = Number(bytes);
        if (!isFinite(bytes) || bytes < 0) bytes = 0;
        if (bytes < 1024) return Math.round(bytes) + ' B';
        var units = ['KB', 'MB', 'GB'];
        var i = -1, v = bytes;
        do { v = v / 1024; i++; } while (v >= 1024 && i < units.length - 1);
        return (Math.round(v * 10) / 10) + ' ' + units[i];
    }

    function extensionFor(mime) {
        if (mime === 'image/jpeg') return 'jpg';
        if (mime === 'image/png') return 'png';
        if (mime === 'image/webp') return 'webp';
        return 'img';
    }

    function baseName(fileName) {
        var n = trim(fileName).replace(/\.[^.\\/]*$/, '');
        return n || 'image';
    }

    function outputFilename(originalName, outMime) {
        return baseName(originalName) + '-compressed.' + extensionFor(outMime);
    }

    // Validate the chosen file before we touch it.
    function validateFile(file) {
        if (!file) return { ok: false, error: 'Choose an image file first.' };
        var type = file.type || '';
        if (ACCEPTED_TYPES.indexOf(type) === -1) {
            if (/^image\//.test(type)) {
                return { ok: false, error: 'This image format is not supported — please use a JPEG, PNG or WebP file.' };
            }
            return { ok: false, error: 'That does not look like an image file — please choose a JPEG, PNG or WebP.' };
        }
        if (file.size > MAX_INPUT_BYTES) {
            return { ok: false, error: 'That file is bigger than 25 MB — it is too heavy to compress in the browser. Try a smaller file first.' };
        }
        if (file.size === 0) {
            return { ok: false, error: 'That file is empty (0 bytes). Please choose a different image.' };
        }
        return { ok: true, error: '' };
    }

    // Fit naturalW x naturalH inside maxW px wide (height scales proportionally).
    // maxW = 0 means "keep original size".
    function scaleDimensions(naturalW, naturalH, maxW) {
        naturalW = Math.floor(Number(naturalW)) || 0;
        naturalH = Math.floor(Number(naturalH)) || 0;
        maxW = Math.floor(Number(maxW)) || 0;
        if (naturalW <= 0 || naturalH <= 0) return { width: 0, height: 0 };
        if (maxW <= 0 || naturalW <= maxW) return { width: naturalW, height: naturalH };
        var ratio = maxW / naturalW;
        return { width: maxW, height: Math.max(1, Math.round(naturalH * ratio)) };
    }

    // Percentage smaller the output is than the input. Negative = grew.
    function savingsPercent(originalBytes, compressedBytes) {
        originalBytes = Number(originalBytes);
        compressedBytes = Number(compressedBytes);
        if (!isFinite(originalBytes) || originalBytes <= 0) return 0;
        if (!isFinite(compressedBytes)) return 0;
        return Math.round((1 - compressedBytes / originalBytes) * 100);
    }

    function isLosslessOut(mime) {
        return mime === 'image/png';
    }

    function qualityApplies(mime) {
        return LOSSY_TYPES.indexOf(mime) !== -1;
    }

    // Honest, plain-English guidance shown next to the format picker.
    function formatAdvice(inputType, outputType) {
        if (outputType === 'image/png') {
            return 'PNG is lossless, so browser compression may only shave a little off. For the biggest savings, pick JPEG or WebP.';
        }
        if (inputType === 'image/png' && (outputType === 'image/jpeg' || outputType === 'image/webp')) {
            return 'Converting a PNG to ' + (outputType === 'image/jpeg' ? 'JPEG' : 'WebP') + ' is where the biggest savings come from — expect dramatic file-size drops.';
        }
        if (outputType === 'image/webp') {
            return 'WebP is the modern sweet spot: visibly identical on screen, but much smaller than JPEG or PNG.';
        }
        return '';
    }

    // JPEG canvases cannot keep transparency — an alpha pixel becomes black.
    // This check lets the UI warn about it before compressing.
    function mayLoseTransparency(inputType, outputType) {
        return inputType === 'image/png' && outputType === 'image/jpeg';
    }

    function emailSizeOk(bytes) {
        return Number(bytes) <= MAX_EMAIL_BYTES;
    }

    function clampQuality(q) {
        q = Math.round(Number(q));
        if (!isFinite(q)) return 80;
        if (q < 10) return 10;
        if (q > 100) return 100;
        return q;
    }

    function verdictLabel(pct) {
        if (pct >= 70) return 'Huge saving';
        if (pct >= 40) return 'Great saving';
        if (pct >= 15) return 'Good saving';
        if (pct >= 1) return 'Small saving';
        if (pct === 0) return 'No change';
        return 'Slightly larger';
    }

    return {
        ACCEPTED_TYPES: ACCEPTED_TYPES,
        MAX_INPUT_BYTES: MAX_INPUT_BYTES,
        MAX_EMAIL_BYTES: MAX_EMAIL_BYTES,
        formatBytes: formatBytes,
        extensionFor: extensionFor,
        baseName: baseName,
        outputFilename: outputFilename,
        validateFile: validateFile,
        scaleDimensions: scaleDimensions,
        savingsPercent: savingsPercent,
        isLosslessOut: isLosslessOut,
        qualityApplies: qualityApplies,
        formatAdvice: formatAdvice,
        mayLoseTransparency: mayLoseTransparency,
        emailSizeOk: emailSizeOk,
        clampQuality: clampQuality,
        verdictLabel: verdictLabel
    };
}));
