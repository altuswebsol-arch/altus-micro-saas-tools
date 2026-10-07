/* Altus Cookie Policy Generator — policy-building logic.
 * Pure functions, no DOM. UMD: works in the browser (window.AltusCPG)
 * and in Node (module.exports) for the test harness.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.AltusCPG = factory();
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var CPG = {};

    CPG.CATEGORIES = [
        {
            key: 'necessary',
            label: 'Strictly necessary cookies',
            short: 'Strictly necessary',
            desc: 'Keep the site working — security, load balancing, remembering your cookie choices. The site cannot function without these.',
            examples: 'For example: cookies that remember your cookie-consent choice, security cookies, and (on WordPress sites) wordpress_test_cookie.'
        },
        {
            key: 'functional',
            label: 'Functional / preference cookies',
            short: 'Functional',
            desc: 'Remember choices you make — such as language or region — so the site feels personal on your next visit.',
            examples: 'For example: a cookie storing your preferred language.'
        },
        {
            key: 'analytics',
            label: 'Analytics / performance cookies',
            short: 'Analytics',
            desc: 'Help us understand how visitors use the site — which pages are popular, where visitors come from — so we can improve it. They do not identify you personally.',
            examples: 'For example: Google Analytics sets cookies such as _ga and _gid to distinguish visits; its main cookie typically lasts up to two years.'
        },
        {
            key: 'marketing',
            label: 'Marketing / advertising cookies',
            short: 'Marketing',
            desc: 'Used to measure advertising campaigns and show relevant ads on other sites. Set by us or by advertising partners.',
            examples: 'For example: cookies set by ad platforms to record that you visited, so later ads can be measured.'
        },
        {
            key: 'embedded',
            label: 'Embedded third-party content',
            short: 'Embedded content',
            desc: 'Some pages include content from other services — such as YouTube videos, Google Maps or social media feeds. Those services may set their own cookies when you view the content.',
            examples: 'For example: watching an embedded YouTube video can set cookies belonging to YouTube/Google.'
        }
    ];

    CPG.clean = function (s) {
        return String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
    };

    CPG.escapeHtml = function (s) {
        return CPG.clean(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };

    CPG.isValidEmail = function (s) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(CPG.clean(s));
    };

    CPG.normaliseUrl = function (s) {
        var u = CPG.clean(s).replace(/\/+$/, '');
        if (u === '') return '';
        u = u.replace(/^http:\/\//i, 'https://');
        if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
        return u;
    };

    CPG.isValidUrl = function (s) {
        var u = CPG.normaliseUrl(s);
        return /^https:\/\/[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}([/?#].*)?$/i.test(u);
    };

    CPG.selectedCategories = function (answers) {
        var sel = (answers && answers.categories) || {};
        return CPG.CATEGORIES.filter(function (c) { return !!sel[c.key]; });
    };

    /* Validate the wizard answers. Returns { ok, errors } where errors
     * maps field name -> message. */
    CPG.validate = function (answers) {
        var errors = {};
        var a = answers || {};
        if (CPG.clean(a.businessName).length < 2) {
            errors.businessName = 'Please enter your business name.';
        }
        if (!CPG.isValidUrl(a.siteUrl)) {
            errors.siteUrl = 'Please enter a valid website address, e.g. example.co.uk.';
        }
        if (!CPG.isValidEmail(a.contactEmail)) {
            errors.contactEmail = 'Please enter a valid contact email address.';
        }
        if (CPG.selectedCategories(a).length === 0) {
            errors.categories = 'Tick at least one cookie type — every website sets strictly necessary cookies at minimum.';
        }
        return { ok: Object.keys(errors).length === 0, errors: errors };
    };

    function catByKey(key) {
        for (var i = 0; i < CPG.CATEGORIES.length; i++) {
            if (CPG.CATEGORIES[i].key === key) return CPG.CATEGORIES[i];
        }
        return null;
    }

    /* Build the policy. Returns { title, lastUpdated, sections[] } where each
     * section is { heading, html }. Nothing is invented: only selected
     * categories and supplied details appear. */
    CPG.buildPolicy = function (answers, lastUpdated) {
        var v = CPG.validate(answers);
        if (!v.ok) throw new Error('Invalid answers: ' + Object.keys(v.errors).join(', '));

        var biz = CPG.clean(answers.businessName);
        var url = CPG.normaliseUrl(answers.siteUrl);
        var email = CPG.clean(answers.contactEmail);
        var other = CPG.clean(answers.otherServices);
        var consentYes = answers.consentTool === 'yes';
        var consentName = CPG.clean(answers.consentToolName);
        var updated = lastUpdated || '';

        var eb = CPG.escapeHtml(biz), eu = CPG.escapeHtml(url), ee = CPG.escapeHtml(email);

        var sections = [];
        function add(heading, html) { sections.push({ heading: heading, html: html }); }

        add('What cookies are',
            '<p>A cookie is a small text file that a website stores on your computer or phone when you visit it. ' +
            'Cookies let the site remember things — keeping you logged in, remembering your preferences, or ' +
            'measuring how visitors use the site. Some cookies are set by us; others are set by third-party ' +
            'services whose content appears on our pages.</p>' +
            '<p>Cookies are not programs and cannot harm your device. You can control or delete them at any ' +
            'time — see \u201CHow to control cookies\u201D below.</p>');

        var cats = CPG.selectedCategories(answers);
        var listHtml = cats.map(function (c) {
            return '<h3>' + CPG.escapeHtml(c.label) + '</h3>' +
                '<p>' + CPG.escapeHtml(c.desc) + '</p>' +
                '<p class="acpg-muted">' + CPG.escapeHtml(c.examples) + '</p>';
        }).join('');

        var intro = '<p>This section describes the cookies used on <strong>' + eu + '</strong>. ' +
            'Only the cookie types you actually use are listed here.</p>';
        add('Cookies we use', intro + listHtml);

        var thirdPartyBits = [];
        if (answers.categories.embedded) thirdPartyBits.push('embedded content providers (for example, video or map services)');
        if (answers.categories.marketing) thirdPartyBits.push('advertising partners');
        if (answers.categories.analytics) thirdPartyBits.push('analytics providers');
        if (other) thirdPartyBits.push(CPG.escapeHtml(other));
        if (thirdPartyBits.length) {
            add('Third-party cookies',
                '<p>Some cookies on our site are set by other organisations: ' + thirdPartyBits.join(', ') + '.</p>' +
                '<p>We do not control these cookies. Each provider publishes its own cookie or privacy information ' +
                'explaining what their cookies do — we recommend checking the relevant provider\u2019s website for details.</p>');
        }

        if (consentYes) {
            add('How we ask for your consent',
                '<p>When you first visit our site, we show a cookie notice' +
                (consentName ? ' (' + CPG.escapeHtml(consentName) + ')' : '') +
                ' that explains the cookies we would like to set and asks for your consent.</p>' +
                '<p><strong>Strictly necessary cookies</strong> are set automatically because the site cannot work ' +
                'without them — they do not need your consent. <strong>All other cookies</strong> are only set ' +
                'after you accept them. You can change your mind at any time using the cookie settings link ' +
                'in our site footer (or by clearing cookies in your browser, which resets your choice).</p>');
        } else {
            add('How we ask for your consent',
                '<p><strong>Strictly necessary cookies</strong> are set automatically because the site cannot work ' +
                'without them — they do not need your consent.</p>' +
                '<p><strong>Important:</strong> you told us you do not currently use a cookie consent banner or tool. ' +
                'Under UK rules (the Privacy and Electronic Communications Regulations), you must get a visitor\u2019s ' +
                'consent <em>before</em> setting any non-essential cookies. If your site sets analytics, marketing, ' +
                'functional or embedded-content cookies, you should add a consent banner that blocks those cookies ' +
                'until the visitor accepts. This policy describes your position honestly — fixing the banner is ' +
                'worth doing promptly.</p>');
        }

        add('How to control cookies',
            '<p>You can control cookies through your browser settings: most browsers let you block some or all ' +
            'cookies, or delete cookies already stored. Blocking cookies may affect how websites work — for ' +
            'example, you may need to re-enter preferences on each visit.</p>' +
            '<p>For step-by-step guidance covering the main browsers, visit ' +
            '<a href="https://www.aboutcookies.org/" target="_blank" rel="noopener">aboutcookies.org</a>, ' +
            'an independent guide to managing cookies.</p>');

        add('Changes to this policy',
            '<p>We may update this cookie policy from time to time — for example, when we add new services to ' +
            'the site. The latest version will always be published on this page' +
            (updated ? ', last updated ' + CPG.escapeHtml(updated) : '') + '.</p>');

        add('Contact us',
            '<p>If you have questions about our use of cookies, contact ' + eb + ' at ' +
            '<a href="mailto:' + ee + '">' + ee + '</a>.</p>');

        return {
            title: 'Cookie Policy \u2014 ' + biz,
            businessName: biz,
            siteUrl: url,
            lastUpdated: updated,
            sections: sections
        };
    };

    /* Renderers */
    CPG.toPlainText = function (policy) {
        var lines = [];
        lines.push(policy.title);
        if (policy.lastUpdated) lines.push('Last updated: ' + policy.lastUpdated);
        lines.push('');
        lines.push('This is a draft template generated from your answers. It is not legal advice — ' +
            'consider having it reviewed before publishing.');
        lines.push('');
        policy.sections.forEach(function (s) {
            lines.push(s.heading.toUpperCase());
            var text = s.html.replace(/<\/p>\s*<p>/g, '\n\n').replace(/<\/h3>/g, '\n')
                .replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/\u2019/g, "'")
                .replace(/\u2014/g, '--').replace(/\u201C/g, '"').replace(/\u201D/g, '"');
            lines.push(text.trim());
            lines.push('');
        });
        return lines.join('\n');
    };

    CPG.toHtmlDoc = function (policy) {
        var body = policy.sections.map(function (s) {
            return '<h2>' + CPG.escapeHtml(s.heading) + '</h2>\n' + s.html;
        }).join('\n');
        return '<!DOCTYPE html>\n<html lang="en-GB">\n<head>\n<meta charset="utf-8">\n' +
            '<title>' + CPG.escapeHtml(policy.title) + '</title>\n</head>\n<body>\n' +
            '<h1>' + CPG.escapeHtml(policy.title) + '</h1>\n' +
            (policy.lastUpdated ? '<p><em>Last updated: ' + CPG.escapeHtml(policy.lastUpdated) + '</em></p>\n' : '') +
            '<p><em>This is a draft template generated from your answers. It is not legal advice — ' +
            'consider having it reviewed before publishing.</em></p>\n' +
            body + '\n</body>\n</html>';
    };

    /* Filename-safe slug for downloads, derived from the business name. */
    CPG.fileSlug = function (businessName) {
        var s = CPG.clean(businessName).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return s ? s.substring(0, 60) : 'cookie-policy';
    };

    return CPG;
}));
