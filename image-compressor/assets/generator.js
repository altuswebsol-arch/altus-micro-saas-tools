/* Altus Image Compressor — UI wiring. Browser only.
 * Uses window.AICLogic (compressor-logic.js) for all pure math.
 * The actual compression happens with <canvas> + toBlob — nothing leaves the browser. */
(function () {
    'use strict';
    var L = window.AICLogic;

    var $ = function (id) { return document.getElementById(id); };

    var drop = $('aic-drop'), fileInput = $('aic-file'), chooseBtn = $('aic-choose'),
        errorEl = $('aic-error'),
        controlsEl = $('aic-controls'), resultsEl = $('aic-results'),
        fmtSel = $('aic-format'), qualityWrap = $('aic-quality-wrap'),
        qualityRange = $('aic-quality'), qualityVal = $('aic-quality-val'),
        maxWidthSel = $('aic-maxwidth'), fmtAdvice = $('aic-format-advice'),
        compressBtn = $('aic-compress'),
        origName = $('aic-orig-name'), origMeta = $('aic-orig-meta'),
        origThumb = $('aic-orig-thumb'), outThumb = $('aic-out-thumb'),
        origSize = $('aic-orig-size'), outSize = $('aic-out-size'),
        savingsEl = $('aic-savings'), dimsEl = $('aic-dims'),
        verdictEl = $('aic-verdict'), downloadBtn = $('aic-download'),
        transWarn = $('aic-trans-warn'), bigWarn = $('aic-big-warn'),
        outDims = $('aic-out-dims');

    var currentFile = null;
    var outBlob = null;
    var outMime = 'image/jpeg';
    var naturalW = 0, naturalH = 0;

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }
    function clearError() {
        errorEl.hidden = true;
        errorEl.textContent = '';
    }

    function effectiveMime() {
        var v = fmtSel.value;
        if (v === 'original') return currentFile ? (currentFile.type || 'image/jpeg') : 'image/jpeg';
        return v;
    }

    function refreshControls() {
        var mime = effectiveMime();
        var lossy = L.qualityApplies(mime);
        qualityWrap.style.display = lossy ? '' : 'none';
        var advice = L.formatAdvice(currentFile ? currentFile.type : '', mime);
        if (advice) { fmtAdvice.textContent = advice; fmtAdvice.hidden = false; }
        else { fmtAdvice.hidden = true; }
        if (L.mayLoseTransparency(currentFile ? currentFile.type : '', mime)) {
            transWarn.hidden = false;
        } else {
            transWarn.hidden = true;
        }
    }

    function updateQualityLabel() {
        qualityVal.textContent = L.clampQuality(qualityRange.value) + '%';
    }

    function setFile(file) {
        clearError();
        var v = L.validateFile(file);
        if (!v.ok) { showError(v.error); return; }
        currentFile = file;
        resultsEl.hidden = true;
        outBlob = null;

        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            naturalW = img.naturalWidth;
            naturalH = img.naturalHeight;
            URL.revokeObjectURL(url);
            origThumb.src = URL.createObjectURL(file);
            document.getElementById('aic-orig-thumb2').src = origThumb.src;
            origName.textContent = file.name;
            origMeta.textContent = L.formatBytes(file.size) + ' · ' + naturalW + ' × ' + naturalH + ' px';
            controlsEl.hidden = false;
            refreshControls();
            drop.classList.add('aic-has-file');
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            showError('We could not read that image — it may be corrupted. Please try a different file.');
            currentFile = null;
        };
        img.src = url;
    }

    chooseBtn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) setFile(fileInput.files[0]);
        fileInput.value = '';
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault(); e.stopPropagation();
            drop.classList.add('aic-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault(); e.stopPropagation();
            drop.classList.remove('aic-dragover');
        });
    });
    drop.addEventListener('drop', function (e) {
        var dt = e.dataTransfer;
        if (dt && dt.files && dt.files[0]) setFile(dt.files[0]);
    });
    // Keyboard access: Enter/Space on the drop zone opens the picker.
    drop.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    fmtSel.addEventListener('change', refreshControls);
    qualityRange.addEventListener('input', updateQualityLabel);
    updateQualityLabel();

    function compress() {
        clearError();
        if (!currentFile) { showError('Choose an image file first.'); return; }
        var mime = effectiveMime();
        var quality = L.clampQuality(qualityRange.value) / 100;
        var dims = L.scaleDimensions(naturalW, naturalH, parseInt(maxWidthSel.value, 10));

        compressBtn.disabled = true;
        compressBtn.textContent = 'Compressing…';

        var url = URL.createObjectURL(currentFile);
        var img = new Image();
        img.onload = function () {
            try {
                var canvas = document.createElement('canvas');
                canvas.width = dims.width;
                canvas.height = dims.height;
                var ctx = canvas.getContext('2d');
                // JPEG has no transparency: paint white behind so transparent
                // PNGs do not come out with a black background.
                if (mime === 'image/jpeg') {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, dims.width, dims.height);
                }
                ctx.drawImage(img, 0, 0, dims.width, dims.height);
                URL.revokeObjectURL(url);

                var blobOpts = L.qualityApplies(mime) ? quality : undefined;
                canvas.toBlob(function (blob) {
                    compressBtn.disabled = false;
                    compressBtn.textContent = 'Compress image';
                    if (!blob) {
                        showError('Your browser could not create that format — please try JPEG or PNG instead.');
                        return;
                    }
                    outBlob = blob;
                    outMime = mime;
                    renderResults(dims);
                }, mime, blobOpts);
            } catch (err) {
                URL.revokeObjectURL(url);
                compressBtn.disabled = false;
                compressBtn.textContent = 'Compress image';
                showError('Something went wrong while compressing. Please try a different image.');
            }
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            compressBtn.disabled = false;
            compressBtn.textContent = 'Compress image';
            showError('We could not read that image — it may be corrupted.');
        };
        img.src = url;
    }

    function renderResults(dims) {
        var pct = L.savingsPercent(currentFile.size, outBlob.size);

        origSize.textContent = L.formatBytes(currentFile.size);
        outSize.textContent = L.formatBytes(outBlob.size);
        savingsEl.textContent = pct + '%';
        savingsEl.className = 'aic-stat-num ' + (pct >= 15 ? 'aic-good' : (pct >= 1 ? 'aic-ok' : 'aic-flat'));
        dimsEl.textContent = naturalW + ' × ' + naturalH + ' px → ' + dims.width + ' × ' + dims.height + ' px';
        outDims.textContent = dims.width + ' × ' + dims.height + ' px';
        verdictEl.textContent = L.verdictLabel(pct) + '.';
        outThumb.src = URL.createObjectURL(outBlob);

        bigWarn.hidden = outBlob.size < currentFile.size;
        downloadBtn.href = outThumb.src;
        downloadBtn.setAttribute('download', L.outputFilename(currentFile.name, outMime));

        // Reset the email gate for the new result.
        $('aic-send-form').hidden = false;
        $('aic-send-done').hidden = true;

        resultsEl.hidden = false;
        resultsEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    compressBtn.addEventListener('click', compress);

    /* ---------------- Email gate: "email me the compressed image" ---------------- */
    var sendForm = $('aic-send-form'), sendBtn = $('aic-send-btn'),
        sendName = $('aic-send-name'), sendEmail = $('aic-send-email');

    function gateError(id, msg) {
        var el = $(id);
        el.textContent = msg;
        el.hidden = !msg;
    }

    sendForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();
        if (!outBlob) { showError('Compress an image first, then ask for the email.'); return; }
        if (!L.emailSizeOk(outBlob.size)) {
            showError('The compressed image is over 2 MB, so we cannot email it — please use the download button above instead.');
            return;
        }

        var name = (sendName.value || '').trim();
        var email = (sendEmail.value || '').trim();
        gateError('aic-e-send-name', name.length < 2 ? 'Please enter your name.' : '');
        gateError('aic-e-send-email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? '' : 'Please enter a valid email address.');
        if (name.length < 2 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;

        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending…';

        var reader = new FileReader();
        reader.onload = function () {
            var b64 = String(reader.result).split(',')[1] || '';
            var data = new FormData();
            data.append('action', 'aic_send_image');
            data.append('nonce', window.AIC.nonce);
            data.append('name', name);
            data.append('email', email);
            data.append('img', b64);
            data.append('mime', outMime);
            data.append('filename', L.outputFilename(currentFile.name, outMime));

            fetch(window.AIC.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Email me my compressed image';
                    if (json && json.success) {
                        sendForm.hidden = true;
                        $('aic-send-done').hidden = false;
                    } else {
                        showError((json && json.data) || 'Sorry, that did not send. Please use the download button above instead.');
                    }
                })
                .catch(function () {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Email me my compressed image';
                    showError('Sorry, that did not send. Please use the download button above instead.');
                });
        };
        reader.readAsDataURL(outBlob);
    });
})();
