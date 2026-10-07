/* Altus Cookie Policy Generator — browser UI.
 * Requires: assets/cookie-builder.js (window.AltusCPG).
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

    function download(filename, content, mime) {
        var blob = new Blob([content], { type: mime });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(url); }, 500);
    }

    var state = { policy: null, plain: '', answers: null };

    function collectAnswers() {
        var cats = {};
        AltusCPG.CATEGORIES.forEach(function (c) {
            var box = $('acpg-cat-' + c.key);
            cats[c.key] = !!(box && box.checked);
        });
        var consent = document.querySelector('input[name="acpg-consent"]:checked');
        return {
            businessName: $('acpg-name').value,
            siteUrl: $('acpg-url').value,
            contactEmail: $('acpg-email').value,
            categories: cats,
            otherServices: $('acpg-other').value,
            consentTool: consent ? consent.value : '',
            consentToolName: $('acpg-consent-name').value
        };
    }

    function gotoStep(n) {
        for (var i = 1; i <= 3; i++) {
            $('acpg-s' + i).hidden = (i !== n);
        }
        var bar = $('acpg-progress-bar');
        if (bar) bar.style.width = (n === 3 ? 100 : n * 33) + '%';
        var dots = document.querySelectorAll('.acpg-dot');
        dots.forEach(function (d, idx) {
            d.classList.toggle('active', idx < n);
        });
        var target = $('acpg-s' + n) || $('acpg-root');
        if (target.scrollIntoView) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function todayUK() {
        try {
            return new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        } catch (e) { return ''; }
    }

    function doGenerate() {
        ['acpg-e-name', 'acpg-e-url', 'acpg-e-email', 'acpg-e-cats', 'acpg-e-consent'].forEach(function (id) { showError(id, ''); });
        var answers = collectAnswers();
        // Consent defaults to "no" only if the user picked neither — force a choice.
        var v = AltusCPG.validate(answers);
        var bad = false;
        if (v.errors.businessName) { showError('acpg-e-name', v.errors.businessName); bad = true; }
        if (v.errors.siteUrl) { showError('acpg-e-url', v.errors.siteUrl); bad = true; }
        if (v.errors.contactEmail) { showError('acpg-e-email', v.errors.contactEmail); bad = true; }
        if (v.errors.categories) { showError('acpg-e-cats', v.errors.categories); bad = true; }
        if (!answers.consentTool) { showError('acpg-e-consent', 'Please tell us whether you use a cookie consent banner or tool.'); bad = true; }
        if (bad) { $('acpg-s2').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }

        var policy, plain;
        try {
            policy = AltusCPG.buildPolicy(answers, todayUK());
            plain = AltusCPG.toPlainText(policy);
        } catch (e) {
            showError('acpg-e-cats', 'Something was wrong with the answers — please check the form and try again.');
            return;
        }
        state.policy = policy;
        state.plain = plain;
        state.answers = answers;

        // Render preview.
        var html = '<p class="acpg-preview-meta"><em>Last updated: ' +
            AltusCPG.escapeHtml(policy.lastUpdated) + '</em></p>' +
            '<p class="acpg-preview-note"><em>Draft template — not legal advice. Consider having it reviewed before publishing.</em></p>';
        policy.sections.forEach(function (s) {
            html += '<h3 class="acpg-preview-h">' + AltusCPG.escapeHtml(s.heading) + '</h3>' + s.html;
        });
        $('acpg-preview').innerHTML = html;

        gotoStep(3);
    }

    function sendPolicy(e) {
        e.preventDefault();
        showError('acpg-e-send-name', '');
        showError('acpg-e-send-email', '');

        var name = $('acpg-send-name').value.trim();
        var email = $('acpg-send-email').value.trim();
        var bad = false;
        if (name.length < 2) { showError('acpg-e-send-name', 'Please enter your name.'); bad = true; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
            showError('acpg-e-send-email', 'Please enter a valid email address.'); bad = true;
        }
        if (bad) return;

        var btn = $('acpg-send-btn');
        btn.disabled = true;
        btn.textContent = 'Sending…';

        var payload = {
            action: 'acpg_send_policy',
            nonce: (window.ACPG && window.ACPG.nonce) || '',
            name: name,
            email: email,
            business_name: state.answers.businessName,
            site_url: AltusCPG.normaliseUrl(state.answers.siteUrl),
            policy: state.plain
        };

        fetch((window.ACPG && window.ACPG.ajax) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(payload).toString()
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.success) {
                $('acpg-send-form').hidden = true;
                $('acpg-send-done').hidden = false;
            } else {
                var msg = (json && json.data && json.data.message) || 'Something went wrong — please try again.';
                showError('acpg-e-send-email', msg);
            }
        }).catch(function () {
            showError('acpg-e-send-email', 'Could not reach the server — check your connection and try again.');
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = 'Email me my cookie policy';
        });
    }

    function wireCategoryCards() {
        AltusCPG.CATEGORIES.forEach(function (c) {
            var box = $('acpg-cat-' + c.key);
            if (!box) return;
            var card = box.closest('.acpg-check-card');
            function sync() { if (card) card.classList.toggle('checked', box.checked); }
            box.addEventListener('change', sync);
            sync();
        });
    }

    onReady(function () {
        if (!$('acpg-root') || typeof AltusCPG === 'undefined') return;

        wireCategoryCards();

        $('acpg-to-s2').addEventListener('click', function () {
            showError('acpg-e-name', ''); showError('acpg-e-url', ''); showError('acpg-e-email', '');
            var a = collectAnswers();
            var bad = false;
            if (AltusCPG.clean(a.businessName).length < 2) { showError('acpg-e-name', 'Please enter your business name.'); bad = true; }
            if (!AltusCPG.isValidUrl(a.siteUrl)) { showError('acpg-e-url', 'Please enter a valid website address, e.g. example.co.uk.'); bad = true; }
            if (!AltusCPG.isValidEmail(a.contactEmail)) { showError('acpg-e-send-email', ''); }
            if (!AltusCPG.isValidEmail(a.contactEmail)) { showError('acpg-e-email', 'Please enter a valid contact email address.'); bad = true; }
            if (bad) return;
            gotoStep(2);
        });
        $('acpg-back-s1').addEventListener('click', function () { gotoStep(1); });
        $('acpg-generate').addEventListener('click', doGenerate);
        $('acpg-start-over').addEventListener('click', function () {
            state.policy = null; state.plain = '';
            $('acpg-send-done').hidden = true;
            $('acpg-send-form').hidden = false;
            gotoStep(1);
        });

        $('acpg-copy').addEventListener('click', function () {
            var btn = this;
            copyText(state.plain, function (ok) {
                btn.textContent = ok ? 'Copied ✓' : 'Copy failed — select the text manually';
                setTimeout(function () { btn.textContent = 'Copy policy text'; }, 2500);
            });
        });
        $('acpg-dl-txt').addEventListener('click', function () {
            download(AltusCPG.fileSlug(state.answers.businessName) + '-cookie-policy.txt', state.plain, 'text/plain;charset=utf-8');
        });
        $('acpg-dl-html').addEventListener('click', function () {
            download(AltusCPG.fileSlug(state.answers.businessName) + '-cookie-policy.html',
                AltusCPG.toHtmlDoc(state.policy), 'text/html;charset=utf-8');
        });

        $('acpg-send-form').addEventListener('submit', sendPolicy);
    });
})();
