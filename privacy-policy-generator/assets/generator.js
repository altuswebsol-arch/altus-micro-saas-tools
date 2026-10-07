/* Altus Privacy Policy Generator — UI logic.
 * Requires: APPG_buildPolicy (policy-builder.js, loaded first). */
(function () {
'use strict';

var $ = function (id) { return document.getElementById(id); };
var steps = ['appg-s1', 'appg-s2', 'appg-s3', 'appg-s4', 'appg-result'];
var current = 0;
var generated = null; // { title, plaintext, sections }

function showStep(n) {
    current = n;
    steps.forEach(function (id, i) { $(id).hidden = (i !== n); });
    var dots = document.querySelectorAll('.appg-dot');
    dots.forEach(function (d, i) {
        d.classList.toggle('active', i === n);
        d.classList.toggle('done', i < n);
    });
    var prog = $('appg-progress-bar');
    if (prog) prog.style.width = ((n + 1) / steps.length * 100) + '%';
    window.scrollTo({ top: $('appg-root').offsetTop - 80, behavior: 'smooth' });
}

function checked(name) {
    return Array.prototype.slice.call(
        document.querySelectorAll('input[name="' + name + '"]:checked')
    ).map(function (el) { return el.value; });
}

function fieldErr(id, msg) {
    var e = $(id);
    if (msg) { e.textContent = msg; e.hidden = false; } else { e.hidden = true; }
}

function validateS1() {
    var ok = true;
    if (!$('appg-name').value.trim()) { fieldErr('appg-e-name', 'Please enter your business name.'); ok = false; }
    else fieldErr('appg-e-name', '');
    var url = $('appg-url').value.trim();
    if (!url || !/^[a-z0-9.-]+\.[a-z]{2,}(\/\S*)?$/i.test(url.replace(/^https?:\/\//i, ''))) {
        fieldErr('appg-e-url', 'Please enter a valid website address, e.g. example.co.uk.'); ok = false;
    } else fieldErr('appg-e-url', '');
    var em = $('appg-email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { fieldErr('appg-e-email', 'Please enter a valid contact email.'); ok = false; }
    else fieldErr('appg-e-email', '');
    return ok;
}

function answers() {
    return {
        business_name: $('appg-name').value.trim(),
        business_type: $('appg-type').value,
        site_url: $('appg-url').value.trim(),
        contact_email: $('appg-email').value.trim(),
        contact_address: $('appg-address').value.trim(),
        data: checked('appg-data'),
        third_parties: checked('appg-tp'),
        cookies: document.querySelector('input[name="appg-cookies"]:checked').value,
        cookie_types: checked('appg-cookie-types'),
        transfers: document.querySelector('input[name="appg-transfers"]:checked').value,
        retention: $('appg-retention').value,
        retention_custom: $('appg-retention-custom').value.trim(),
        children: document.querySelector('input[name="appg-children"]:checked').value
    };
}

function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function renderBulletParas(paras) {
    // Groups consecutive "• " lines into <ul>, everything else into <p>.
    var html = '', inList = false;
    paras.forEach(function (p) {
        if (/^•\s/.test(p)) {
            if (!inList) { html += '<ul class="appg-doc-list">'; inList = true; }
            html += '<li>' + escapeHtml(p.replace(/^•\s*/, '')) + '</li>';
        } else {
            if (inList) { html += '</ul>'; inList = false; }
            html += '<p>' + escapeHtml(p) + '</p>';
        }
    });
    if (inList) html += '</ul>';
    return html;
}

function renderDoc(g) {
    var html = '<div class="appg-doc-head"><h3>' + escapeHtml(g.title) + '</h3>' +
        '<p class="appg-doc-updated">Last updated: ' + escapeHtml(g.last_updated) + '</p></div>';
    g.sections.forEach(function (s, i) {
        html += '<h4 class="appg-doc-h">' + (i + 1) + '. ' + escapeHtml(s.heading) + '</h4>' +
            renderBulletParas(s.paras);
    });
    html += '<div class="appg-doc-disclaimer"><strong>Important:</strong> this policy was generated as a draft template ' +
        'by the Altus Web Solutions free Privacy Policy Generator. It is not legal advice. ' +
        'Have it reviewed by a solicitor before publishing it on your website.</div>';
    return html;
}

function download(filename, text, mime) {
    var blob = new Blob([text], { type: mime });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
}

function slug(s) {
    return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'privacy-policy';
}

function toHTMLDoc(g) {
    var css = 'body{font-family:Georgia,serif;max-width:760px;margin:40px auto;padding:0 20px;color:#1a1a1a;line-height:1.7}' +
        'h1{font-size:26px}h2{font-size:18px;margin-top:2em;color:#0a2a5c}ul{margin:0.6em 0}.disc{background:#fff8e1;border:1px solid #f0d97a;padding:14px;margin-top:2em;font-size:14px}';
    return '<!DOCTYPE html><html lang="en-GB"><head><meta charset="utf-8"><title>' +
        escapeHtml(g.title) + '</title><style>' + css + '</style></head><body>' +
        '<h1>' + escapeHtml(g.title) + '</h1><p><em>Last updated: ' + escapeHtml(g.last_updated) + '</em></p>' +
        g.sections.map(function (s, i) {
            return '<h2>' + (i + 1) + '. ' + escapeHtml(s.heading) + '</h2>' + renderBulletParas(s.paras);
        }).join('') +
        '<div class="disc"><strong>Important:</strong> this policy was generated as a draft template by the Altus Web Solutions free Privacy Policy Generator. It is not legal advice. Have it reviewed by a solicitor before publishing it on your website.</div>' +
        '</body></html>';
}

function generate() {
    var a = answers();
    generated = APPG_buildPolicy.build(a);
    $('appg-doc-preview').innerHTML = renderDoc(generated);
    $('appg-result-count').textContent = generated.sections.length + ' sections generated for ' + a.business_name + '.';
    showStep(4);
}

function init() {
    if (!$('appg-root')) return;

    $('appg-next-1').addEventListener('click', function () { if (validateS1()) showStep(1); });
    $('appg-next-2').addEventListener('click', function () {
        if (!checked('appg-data').length) { fieldErr('appg-e-data', 'Tick at least one option — or regenerate the page and leave this section for later. Actually: choose the data you collect.'); return; }
        fieldErr('appg-e-data', ''); showStep(2);
    });
    $('appg-next-3').addEventListener('click', function () { showStep(3); });
    $('appg-generate').addEventListener('click', generate);

    // Back buttons
    [['appg-back-2', 0], ['appg-back-3', 1], ['appg-back-4', 2]].forEach(function (pair) {
        $(pair[0]).addEventListener('click', function () { showStep(pair[1]); });
    });
    $('appg-edit').addEventListener('click', function () { showStep(0); });

    // Retention custom toggle
    $('appg-retention').addEventListener('change', function () {
        $('appg-retention-custom-wrap').hidden = (this.value !== 'custom');
    });

    // Cookie types toggle
    Array.prototype.forEach.call(document.querySelectorAll('input[name="appg-cookies"]'), function (r) {
        r.addEventListener('change', function () {
            $('appg-cookie-types-wrap').hidden = (document.querySelector('input[name="appg-cookies"]:checked').value !== 'yes');
        });
    });

    // "None" exclusive checkbox for third parties
    var noneBox = document.querySelector('input[name="appg-tp"][value="none"]');
    var tpBoxes = Array.prototype.slice.call(document.querySelectorAll('input[name="appg-tp"]'));
    noneBox.addEventListener('change', function () {
        if (noneBox.checked) tpBoxes.forEach(function (b) { if (b !== noneBox) b.checked = false; });
    });
    tpBoxes.forEach(function (b) {
        if (b !== noneBox) b.addEventListener('change', function () { if (b.checked) noneBox.checked = false; });
    });

    // Downloads + copy
    $('appg-copy').addEventListener('click', function () {
        var self = this;
        navigator.clipboard.writeText(generated.plaintext).then(function () {
            self.textContent = 'Copied ✓';
            setTimeout(function () { self.textContent = 'Copy to clipboard'; }, 2000);
        });
    });
    $('appg-dl-txt').addEventListener('click', function () {
        download(slug(answers().business_name) + '-privacy-policy.txt', generated.plaintext, 'text/plain;charset=utf-8');
    });
    $('appg-dl-html').addEventListener('click', function () {
        download(slug(answers().business_name) + '-privacy-policy.html', toHTMLDoc(generated), 'text/html;charset=utf-8');
    });

    // Email gate
    $('appg-send-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var nm = $('appg-send-name').value.trim();
        var em = $('appg-send-email').value.trim();
        var ok = true;
        if (!nm) { fieldErr('appg-e-send-name', 'Please enter your name.'); ok = false; } else fieldErr('appg-e-send-name', '');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { fieldErr('appg-e-send-email', 'Please enter a valid email.'); ok = false; } else fieldErr('appg-e-send-email', '');
        if (!ok) return;
        var btn = $('appg-send-btn');
        btn.disabled = true; btn.textContent = 'Sending…';
        var a = answers();
        var payload = new URLSearchParams();
        payload.append('action', 'appg_send_policy');
        payload.append('nonce', APPG.nonce);
        payload.append('name', nm);
        payload.append('email', em);
        payload.append('business_name', a.business_name);
        payload.append('site_url', a.site_url);
        payload.append('policy', generated.plaintext);
        fetch(APPG.ajax, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success) {
                    $('appg-send-form').hidden = true;
                    $('appg-send-done').hidden = false;
                } else {
                    fieldErr('appg-e-send-email', (res && res.data && res.data.message) || 'Something went wrong — please try again.');
                    btn.disabled = false; btn.textContent = 'Email me this policy';
                }
            })
            .catch(function () {
                fieldErr('appg-e-send-email', 'Could not reach the server — please try again.');
                btn.disabled = false; btn.textContent = 'Email me this policy';
            });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
})();
