/* Altus Meta Description Checker — UI wiring.
 * Requires window.MDC (assets/meta-logic.js) loaded first.
 */
(function () {
    'use strict';
    if (!window.MDC) return;
    var M = window.MDC;

    function $(id) { return document.getElementById(id); }

    var els = {};
    ['mdc-tab-url', 'mdc-tab-text', 'mdc-pane-url', 'mdc-pane-text',
     'mdc-url', 'mdc-fetch', 'mdc-fetch-msg',
     'mdc-title', 'mdc-desc', 'mdc-keyword',
     'mdc-desc-count', 'mdc-title-count',
     'mdc-check', 'mdc-error',
     'mdc-results', 'mdc-score-num', 'mdc-score-grade', 'mdc-score-bar',
     'mdc-prev-title', 'mdc-prev-url', 'mdc-prev-desc', 'mdc-prev-note',
     'mdc-prev-title-m', 'mdc-prev-url-m', 'mdc-prev-desc-m',
     'mdc-prev-desktop', 'mdc-prev-mobile', 'mdc-view-desktop', 'mdc-view-mobile',
     'mdc-checks-desc', 'mdc-checks-title',
     'mdc-copy', 'mdc-download', 'mdc-copy-msg',
     'mdc-send-form', 'mdc-send-name', 'mdc-send-email',
     'mdc-e-send-name', 'mdc-e-send-email', 'mdc-send-btn',
     'mdc-send-done'
    ].forEach(function (id) { els[id] = $(id); });
    if (!els['mdc-check']) return;

    var state = { url: '', title: '', desc: '', keyword: '', score: 0, grade: '', dChecks: [], tChecks: [] };

    /* ---- tabs ---- */
    function setTab(which) {
        var url = which === 'url';
        els['mdc-tab-url'].classList.toggle('mdc-tab-active', url);
        els['mdc-tab-text'].classList.toggle('mdc-tab-active', !url);
        els['mdc-pane-url'].hidden = !url;
        els['mdc-pane-text'].hidden = url;
    }
    els['mdc-tab-url'].addEventListener('click', function () { setTab('url'); });
    els['mdc-tab-text'].addEventListener('click', function () { setTab('text'); });

    /* ---- live counters ---- */
    function counters() {
        var d = els['mdc-desc'].value, t = els['mdc-title'].value;
        els['mdc-desc-count'].textContent = M.clean(d).length + ' / 155 characters';
        els['mdc-title-count'].textContent = M.clean(t).length + ' characters';
    }
    els['mdc-desc'].addEventListener('input', counters);
    els['mdc-title'].addEventListener('input', counters);

    /* ---- fetch a live URL ---- */
    els['mdc-fetch'].addEventListener('click', function () {
        var url = els['mdc-url'].value.trim();
        hideError();
        if (!/^https?:\/\//i.test(url)) {
            if (/^[\w.-]+\.[a-z]{2,}/i.test(url)) url = 'https://' + url;
            else { showError('Please enter a full URL, e.g. https://example.com/your-page'); return; }
        }
        els['mdc-fetch-msg'].hidden = false;
        els['mdc-fetch-msg'].textContent = 'Fetching the page…';
        els['mdc-fetch'].disabled = true;
        var body = new URLSearchParams({
            action: 'mdc_fetch_page', nonce: MDC_AJAX.nonce, url: url
        });
        fetch(MDC_AJAX.ajax, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); })
        .then(function (res) {
            els['mdc-fetch'].disabled = false;
            if (!res.success) {
                els['mdc-fetch-msg'].textContent = '';
                els['mdc-fetch-msg'].hidden = true;
                showError(res.data || 'Could not read that page.');
                return;
            }
            state.url = res.data.url;
            els['mdc-title'].value = res.data.title || '';
            els['mdc-desc'].value = res.data.description || '';
            els['mdc-fetch-msg'].textContent = res.data.description
                ? 'Got it — title and meta description loaded below. Edit them if you like, then press Check.'
                : 'Got the page, but it has no meta description — the title is loaded below. Write one and press Check.';
            counters();
            runCheck();
        })
        .catch(function () {
            els['mdc-fetch'].disabled = false;
            els['mdc-fetch-msg'].hidden = true;
            showError('Could not reach that page. Check the URL and try again.');
        });
    });

    /* ---- run the check ---- */
    function showError(msg) {
        els['mdc-error'].textContent = msg;
        els['mdc-error'].hidden = false;
    }
    function hideError() { els['mdc-error'].hidden = true; }

    function checkRow(c) {
        var icon = c.status === 'pass' ? '✓' : (c.status === 'warn' ? '!' : '✕');
        return '<li class="mdc-check mdc-' + c.status + '">' +
            '<span class="mdc-check-icon" aria-hidden="true">' + icon + '</span>' +
            '<div><strong>' + escapeHtml(c.label) + '</strong>' +
            '<p>' + escapeHtml(c.detail) + '</p></div></li>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function runCheck() {
        hideError();
        var title = els['mdc-title'].value,
            desc = els['mdc-desc'].value,
            keyword = els['mdc-keyword'].value;
        if (!M.clean(title) && !M.clean(desc)) {
            showError('Enter a page title and a meta description — or fetch them from a URL above.');
            return;
        }
        var d = M.analyzeDescription(desc, title, keyword);
        var t = M.analyzeTitle(title, keyword);
        var score = M.scoreAll(d.checks, t.checks);
        var grade = M.gradeFor(score);

        state = { url: state.url, title: M.clean(title), desc: M.clean(desc),
                  keyword: M.clean(keyword), score: score, grade: grade.label,
                  dChecks: d.checks, tChecks: t.checks };

        /* score */
        els['mdc-score-num'].textContent = score;
        els['mdc-score-grade'].textContent = grade.label;
        els['mdc-score-grade'].className = 'mdc-grade mdc-' + grade.cls;
        els['mdc-score-bar'].style.width = score + '%';
        els['mdc-score-bar'].className = 'mdc-score-fill mdc-' + grade.cls;

        /* SERP preview */
        var shownTitle = M.clean(title) || '(no title)';
        var shownDesc = M.clean(desc) || '(no meta description — Google will pick text from your page)';
        els['mdc-prev-title'].textContent = M.truncateToWidth(shownTitle, M.TITLE_MAX_PX, M.TITLE_FONT);
        els['mdc-prev-url'].textContent = prettyUrl(state.url);
        els['mdc-prev-desc'].textContent = M.truncateToWidth(shownDesc, M.DESC_MAX_PX, M.DESC_FONT);
        els['mdc-prev-title-m'].textContent = M.truncateToWidth(shownTitle, M.TITLE_MAX_PX, M.TITLE_FONT);
        els['mdc-prev-url-m'].textContent = prettyUrl(state.url);
        els['mdc-prev-desc-m'].textContent = M.truncateToWidth(shownDesc, M.DESC_MOBILE_PX, M.DESC_FONT);
        els['mdc-prev-note'].textContent = d.truncated
            ? 'This preview is cut where Google would cut it (about 920 px).'
            : 'Shown at full length — this is roughly what searchers would see.';

        /* checklists */
        els['mdc-checks-desc'].innerHTML = d.checks.map(checkRow).join('');
        els['mdc-checks-title'].innerHTML = t.checks.map(checkRow).join('');

        els['mdc-results'].hidden = false;
        els['mdc-results'].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    els['mdc-check'].addEventListener('click', runCheck);

    function prettyUrl(u) {
        if (!u) return 'yourwebsite.com › your-page';
        return u.replace(/^https?:\/\//i, '').replace(/\/$/, '').split('/').join(' › ');
    }

    /* ---- preview device toggle ---- */
    els['mdc-view-desktop'].addEventListener('click', function () { setView('desktop'); });
    els['mdc-view-mobile'].addEventListener('click', function () { setView('mobile'); });
    function setView(v) {
        var mob = v === 'mobile';
        els['mdc-view-desktop'].classList.toggle('mdc-tab-active', !mob);
        els['mdc-view-mobile'].classList.toggle('mdc-tab-active', mob);
        els['mdc-prev-desktop'].hidden = mob;
        els['mdc-prev-mobile'].hidden = !mob;
    }

    /* ---- copy + download report ---- */
    function reportText() {
        return M.buildReport(state.url, state.title, state.desc, state.keyword,
                             state.score, state.grade, state.dChecks, state.tChecks);
    }
    els['mdc-copy'].addEventListener('click', function () {
        var done = function () {
            els['mdc-copy-msg'].textContent = 'Copied to clipboard.';
            els['mdc-copy-msg'].hidden = false;
            setTimeout(function () { els['mdc-copy-msg'].hidden = true; }, 2500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(reportText()).then(done, function () { fallbackCopy(done); });
        } else fallbackCopy(done);
    });
    function fallbackCopy(done) {
        var ta = document.createElement('textarea');
        ta.value = reportText();
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
        document.body.removeChild(ta);
    }
    els['mdc-download'].addEventListener('click', function () {
        var blob = new Blob([reportText()], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'meta-description-report.txt';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(a.href);
            document.body.removeChild(a);
        }, 500);
    });

    /* ---- email gate ---- */
    function fieldError(input, msgEl, msg) {
        msgEl.textContent = msg;
        msgEl.hidden = !msg;
        input.classList.toggle('mdc-input-bad', !!msg);
        return !msg;
    }
    els['mdc-send-form'].addEventListener('submit', function (e) {
        e.preventDefault();
        var nameOk = fieldError(els['mdc-send-name'], els['mdc-e-send-name'],
            els['mdc-send-name'].value.trim().length >= 2 ? '' : 'Please enter your name.');
        var emailOk = fieldError(els['mdc-send-email'], els['mdc-e-send-email'],
            /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(els['mdc-send-email'].value.trim()) ? '' : 'Please enter a valid email address.');
        if (!nameOk || !emailOk) return;

        els['mdc-send-btn'].disabled = true;
        els['mdc-send-btn'].textContent = 'Sending…';
        var body = new URLSearchParams({
            action: 'mdc_send_report', nonce: MDC_AJAX.nonce,
            name: els['mdc-send-name'].value.trim(),
            email: els['mdc-send-email'].value.trim(),
            report: reportText()
        });
        fetch(MDC_AJAX.ajax, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); })
        .then(function (res) {
            els['mdc-send-btn'].disabled = false;
            els['mdc-send-btn'].textContent = 'Email me my report';
            if (res.success) {
                els['mdc-send-form'].hidden = true;
                els['mdc-send-done'].hidden = false;
            } else {
                fieldError(els['mdc-send-email'], els['mdc-e-send-email'], res.data || 'Sorry, that did not send. Try the download button instead.');
            }
        })
        .catch(function () {
            els['mdc-send-btn'].disabled = false;
            els['mdc-send-btn'].textContent = 'Email me my report';
            fieldError(els['mdc-send-email'], els['mdc-e-send-email'], 'Sorry, that did not send. Try the download button instead.');
        });
    });

    counters();
})();
