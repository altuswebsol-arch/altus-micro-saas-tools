/* Altus Website Grader — frontend behaviour. */
(function () {
    'use strict';
    if (typeof AWG === 'undefined') return;

    var root = document.getElementById('awg-root');
    if (!root) return;

    var steps = {
        url: document.getElementById('awg-step-url'),
        scan: document.getElementById('awg-step-scan'),
        result: document.getElementById('awg-step-result'),
        report: document.getElementById('awg-step-report')
    };

    function show(name) {
        Object.keys(steps).forEach(function (k) { steps[k].hidden = (k !== name); });
        steps[name].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function err(el, msg) {
        el.textContent = msg;
        el.hidden = !msg;
    }

    function setRing(ringEl, numEl, score) {
        ringEl.style.setProperty('--awg-p', score);
        var start = null, dur = 900;
        function tick(t) {
            if (!start) start = t;
            var p = Math.min((t - start) / dur, 1);
            numEl.textContent = Math.round(score * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    function statusLabel(s) {
        if (s === 'good') return { text: 'Good', cls: 'good' };
        if (s === 'improve') return { text: 'Could improve', cls: 'improve' };
        return { text: 'Needs work', cls: 'fail' };
    }

    /* ---------- Step 1: run the check ---------- */
    var scanTimer = null;
    var scanMessages = [
        'Loading homepage…',
        'Measuring server response time…',
        'Checking mobile & security basics…',
        'Analysing SEO signals…',
        'Scoring your website…'
    ];

    document.getElementById('awg-url-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var urlInput = document.getElementById('awg-url');
        var url = urlInput.value.trim();
        var urlErr = document.getElementById('awg-url-error');
        if (!url) { err(urlErr, 'Please enter your website address.'); return; }
        err(urlErr, '');

        var btn = document.getElementById('awg-run-btn');
        btn.disabled = true;
        btn.querySelector('.awg-btn-text').textContent = 'Grading…';
        show('scan');

        var bar = document.getElementById('awg-progress-bar');
        var statusEl = document.getElementById('awg-scan-status');
        var i = 0, pct = 5;
        bar.style.width = '5%';
        scanTimer = setInterval(function () {
            i = Math.min(i + 1, scanMessages.length - 1);
            statusEl.textContent = scanMessages[i];
            pct = Math.min(pct + 18, 92);
            bar.style.width = pct + '%';
        }, 5000);

        var fd = new FormData();
        fd.append('action', 'awg_run');
        fd.append('nonce', AWG.nonce);
        fd.append('url', url);
        fd.append('business_name', document.getElementById('awg-business-name').value.trim());

        fetch(AWG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                clearInterval(scanTimer);
                bar.style.width = '100%';
                btn.disabled = false;
                btn.querySelector('.awg-btn-text').textContent = 'Grade my website';
                if (!res.success) { show('url'); err(urlErr, res.data.message || 'Something went wrong. Please try again.'); return; }
                renderResult(res.data);
            })
            .catch(function () {
                clearInterval(scanTimer);
                btn.disabled = false;
                btn.querySelector('.awg-btn-text').textContent = 'Grade my website';
                show('url');
                err(urlErr, 'The check took too long or the connection dropped. Please try again.');
            });
    });

    var currentToken = null;

    function renderResult(d) {
        currentToken = d.token;
        setRing(document.getElementById('awg-score-ring'), document.getElementById('awg-score-num'), d.score);
        document.getElementById('awg-grade').textContent = 'Grade ' + d.grade;
        document.getElementById('awg-result-summary').textContent = d.summary;

        var ul = document.getElementById('awg-top-issues');
        ul.innerHTML = '';
        d.issues.forEach(function (it) {
            var s = statusLabel(it.status);
            var li = document.createElement('li');
            if (it.status === 'improve') li.className = 'awg-warn';
            li.innerHTML =
                '<div><span class="awg-issue-label"></span> <span class="awg-issue-status ' + s.cls + '"></span></div>' +
                '<div class="awg-issue-why"></div>';
            li.querySelector('.awg-issue-label').textContent = it.label;
            li.querySelector('.awg-issue-status').textContent = ' — ' + s.text;
            li.querySelector('.awg-issue-why').textContent = it.detail + ' ' + it.why;
            ul.appendChild(li);
        });
        show('result');
    }

    /* ---------- Step 3: unlock the full report ---------- */
    document.getElementById('awg-unlock-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var name = document.getElementById('awg-name').value.trim();
        var email = document.getElementById('awg-email').value.trim();
        var uErr = document.getElementById('awg-unlock-error');
        if (!name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            err(uErr, 'Please enter your name and a valid email address.');
            return;
        }
        err(uErr, '');

        var btn = document.getElementById('awg-unlock-btn');
        btn.disabled = true;
        btn.querySelector('.awg-btn-text').textContent = 'Sending…';

        var fd = new FormData();
        fd.append('action', 'awg_unlock');
        fd.append('nonce', AWG.nonce);
        fd.append('token', currentToken || '');
        fd.append('name', name);
        fd.append('email', email);

        fetch(AWG.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.querySelector('.awg-btn-text').textContent = 'Email me the full report';
                if (!res.success) { err(uErr, res.data.message || 'Something went wrong. Please try again.'); return; }
                renderReport(res.data);
            })
            .catch(function () {
                btn.disabled = false;
                btn.querySelector('.awg-btn-text').textContent = 'Email me the full report';
                err(uErr, 'Something went wrong. Please try again.');
            });
    });

    function renderReport(d) {
        setRing(document.getElementById('awg-score-ring-2'), document.getElementById('awg-score-num-2'), d.score);
        document.getElementById('awg-report-meta').textContent =
            d.url + ' — scored ' + d.score + '% (Grade ' + d.grade + '). A copy has been emailed to you.';
        document.getElementById('awg-report-note').textContent = d.note;
        var book = document.getElementById('awg-book-link');
        if (d.book_url) book.href = d.book_url;

        var tbody = document.querySelector('#awg-report-table tbody');
        tbody.innerHTML = '';
        d.items.forEach(function (it, idx) {
            var s = statusLabel(it.status);
            var tr = document.createElement('tr');
            tr.style.animationDelay = Math.min(idx * 0.03, 0.5) + 's';
            var td1 = document.createElement('td');
            td1.textContent = it.label;
            var td2 = document.createElement('td');
            var sp = document.createElement('span'); sp.className = 'awg-status ' + s.cls; sp.textContent = s.text;
            td2.appendChild(sp);
            var td3 = document.createElement('td');
            var det = document.createElement('div'); det.className = 'awg-detail'; det.textContent = it.detail;
            var why = document.createElement('div'); why.className = 'awg-why'; why.textContent = it.why;
            td3.appendChild(det); td3.appendChild(why);
            tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
            tbody.appendChild(tr);
        });
        show('report');
    }
})();
