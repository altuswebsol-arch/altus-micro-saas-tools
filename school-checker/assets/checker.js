/* Altus School Website Compliance Checker — frontend behaviour. */
(function () {
    'use strict';
    if (typeof ASC === 'undefined') return;

    var root = document.getElementById('asc-root');
    if (!root) return;

    var steps = {
        url: document.getElementById('asc-step-url'),
        scan: document.getElementById('asc-step-scan'),
        result: document.getElementById('asc-step-result'),
        report: document.getElementById('asc-step-report')
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
        ringEl.style.setProperty('--asc-p', score);
        // Animate the number counting up.
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
        if (s === 'found') return { text: 'Found', cls: 'found' };
        if (s === 'uncertain') return { text: 'Check manually', cls: 'uncertain' };
        return { text: 'Not found', cls: 'missing' };
    }

    /* ---------- Step 1: run the check ---------- */
    var scanTimer = null;
    var scanMessages = [
        'Loading homepage…',
        'Checking mobile & security basics…',
        'Discovering internal pages…',
        'Scanning policies and documents…',
        'Checking Ofsted & DfE items…',
        'Scoring your website…'
    ];

    document.getElementById('asc-url-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var urlInput = document.getElementById('asc-url');
        var url = urlInput.value.trim();
        var urlErr = document.getElementById('asc-url-error');
        if (!url) { err(urlErr, 'Please enter your school website address.'); return; }
        err(urlErr, '');

        var btn = document.getElementById('asc-run-btn');
        btn.disabled = true;
        btn.querySelector('.asc-btn-text').textContent = 'Checking…';
        show('scan');

        // Cosmetic progress while the server crawls.
        var bar = document.getElementById('asc-progress-bar');
        var statusEl = document.getElementById('asc-scan-status');
        var i = 0, pct = 5;
        bar.style.width = '5%';
        scanTimer = setInterval(function () {
            i = Math.min(i + 1, scanMessages.length - 1);
            statusEl.textContent = scanMessages[i];
            pct = Math.min(pct + 14, 92);
            bar.style.width = pct + '%';
        }, 7000);

        var fd = new FormData();
        fd.append('action', 'asc_run');
        fd.append('nonce', ASC.nonce);
        fd.append('url', url);
        fd.append('school_name', document.getElementById('asc-school-name').value.trim());

        fetch(ASC.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                clearInterval(scanTimer);
                bar.style.width = '100%';
                btn.disabled = false;
                btn.querySelector('.asc-btn-text').textContent = 'Run free check';
                if (!res.success) { show('url'); err(urlErr, res.data.message || 'Something went wrong. Please try again.'); return; }
                renderResult(res.data);
            })
            .catch(function () {
                clearInterval(scanTimer);
                btn.disabled = false;
                btn.querySelector('.asc-btn-text').textContent = 'Run free check';
                show('url');
                err(urlErr, 'The check took too long or the connection dropped. Please try again.');
            });
    });

    var currentToken = null;

    function renderResult(d) {
        currentToken = d.token;
        setRing(document.getElementById('asc-score-ring'), document.getElementById('asc-score-num'), d.score);
        document.getElementById('asc-grade').textContent = 'Grade ' + d.grade;
        document.getElementById('asc-result-summary').textContent = d.summary;

        var ul = document.getElementById('asc-top-issues');
        ul.innerHTML = '';
        d.issues.forEach(function (it) {
            var s = statusLabel(it.status);
            var li = document.createElement('li');
            if (it.status === 'uncertain') li.className = 'asc-warn';
            li.innerHTML =
                '<div><span class="asc-issue-label"></span> <span class="asc-issue-status ' + s.cls + '"></span></div>' +
                '<div class="asc-issue-why"></div>';
            li.querySelector('.asc-issue-label').textContent = it.label;
            li.querySelector('.asc-issue-status').textContent = ' — ' + s.text;
            li.querySelector('.asc-issue-why').textContent = it.why;
            ul.appendChild(li);
        });
        show('result');
    }

    /* ---------- Step 3: unlock the full report ---------- */
    document.getElementById('asc-unlock-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var name = document.getElementById('asc-name').value.trim();
        var email = document.getElementById('asc-email').value.trim();
        var phone = document.getElementById('asc-phone').value.trim();
        var uErr = document.getElementById('asc-unlock-error');
        if (!name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || phone.replace(/\D/g, '').length < 7) {
            err(uErr, 'Please enter your name, a valid email address, and a phone number we can reach you on.');
            return;
        }
        err(uErr, '');

        var btn = document.getElementById('asc-unlock-btn');
        btn.disabled = true;
        btn.querySelector('.asc-btn-text').textContent = 'Sending…';

        var fd = new FormData();
        fd.append('action', 'asc_unlock');
        fd.append('nonce', ASC.nonce);
        fd.append('token', currentToken || '');
        fd.append('name', name);
        fd.append('email', email);
        fd.append('phone', phone);

        fetch(ASC.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.querySelector('.asc-btn-text').textContent = 'Email me the full report';
                if (!res.success) { err(uErr, res.data.message || 'Something went wrong. Please try again.'); return; }
                renderReport(res.data);
            })
            .catch(function () {
                btn.disabled = false;
                btn.querySelector('.asc-btn-text').textContent = 'Email me the full report';
                err(uErr, 'Something went wrong. Please try again.');
            });
    });

    function renderReport(d) {
        setRing(document.getElementById('asc-score-ring-2'), document.getElementById('asc-score-num-2'), d.score);
        document.getElementById('asc-report-meta').textContent =
            d.url + ' — scored ' + d.score + '% (Grade ' + d.grade + '). A copy has been emailed to you.';
        document.getElementById('asc-report-note').textContent = d.note;
        var book = document.getElementById('asc-book-link');
        if (d.book_url) book.href = d.book_url;

        var tbody = document.querySelector('#asc-report-table tbody');
        tbody.innerHTML = '';
        d.items.forEach(function (it, idx) {
            var s = statusLabel(it.status);
            var tr = document.createElement('tr');
            tr.style.animationDelay = Math.min(idx * 0.03, 0.6) + 's';
            var td1 = document.createElement('td');
            td1.textContent = it.label;
            if (it.statutory === false) {
                var tag = document.createElement('span');
                tag.className = 'asc-rec-tag';
                tag.textContent = 'Recommended';
                td1.appendChild(document.createTextNode(' '));
                td1.appendChild(tag);
            }
            var td2 = document.createElement('td');
            var sp = document.createElement('span'); sp.className = 'asc-status ' + s.cls; sp.textContent = s.text;
            td2.appendChild(sp);
            var td3 = document.createElement('td'); td3.className = 'asc-why'; td3.textContent = it.why;
            tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
            tbody.appendChild(tr);
        });
        show('report');
    }
})();
