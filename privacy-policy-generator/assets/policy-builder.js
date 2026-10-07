/**
 * Altus Privacy Policy Generator — pure policy-text builder.
 * No DOM, no WordPress: unit-testable under Node.
 *
 * Input `a` (answers) shape:
 * {
 *   business_name: string,
 *   business_type: 'sole-trader'|'limited-company'|'partnership'|'charity'|'freelancer',
 *   site_url: string,
 *   contact_email: string,
 *   contact_address: string (''),
 *   data: [ 'names','emails','phones','addresses','payments','accounts','enquiries','analytics','marketing_prefs' ],
 *   third_parties: [ 'google-analytics','meta','google-maps','payment-processors','email-marketing','hosting','chat','booking' ] (may contain 'none'),
 *   cookies: 'yes'|'no',
 *   cookie_types: [ 'essential','preferences','analytics','marketing' ],
 *   transfers: 'yes'|'no',
 *   retention: 'active-only'|'active-plus-6y'|'enquiries-12m'|'custom',
 *   retention_custom: string (''),
 *   children: 'yes'|'no'
 * }
 *
 * Returns { title, last_updated, sections: [{heading, paras:[...]}], plaintext }
 * Everything the template renders must come from real answers; the generator
 * invents no facts (e.g. it never claims cookies exist if the user said no).
 */

(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.APPG_buildPolicy = factory();
})(typeof self !== 'undefined' ? self : this, function () {

'use strict';

var DATA_LABELS = {
    names:           'your name',
    emails:          'your email address',
    phones:          'your phone number',
    addresses:       'your postal address',
    payments:        'payment details (for example card or billing information needed to take payment)',
    accounts:        'account details such as a username and password, if you create an account with us',
    enquiries:       'the contents of messages you send us through our contact or enquiry forms',
    analytics:      'technical information about how you use our website (such as pages visited, device type and browser)',
    marketing_prefs: 'your marketing preferences (whether you want to hear from us)'
};

var TP_LABELS = {
    'google-analytics': 'Google Analytics — helps us understand how visitors use our website',
    'meta':             'Meta (Facebook/Instagram) tools — used for measuring our advertising and website embeds',
    'google-maps':      'Google Maps — used where we embed maps on our website',
    'payment-processors':'payment processors (for example Stripe or PayPal) — used to take payments securely',
    'email-marketing':  'email marketing platforms (for example Mailchimp) — used to send newsletters you have signed up for',
    'hosting':          'our website hosting provider — stores the files that make up our website',
    'chat':             'live chat or chatbot tools — used when you talk to us through the chat widget',
    'booking':          'booking or scheduling tools — used when you book an appointment or consultation with us'
};

var COOKIE_LABELS = {
    essential:   'strictly necessary cookies — these make the site work (for example remembering your cookie choice)',
    preferences: 'preference cookies — these remember choices you make, such as language or layout',
    analytics:   'analytics cookies — these help us measure how visitors use the site so we can improve it',
    marketing:   'marketing cookies — these are used to show you relevant advertising and measure it'
};

var TYPE_LABELS = {
    'sole-trader':   'sole trader',
    'limited-company':'limited company',
    charity:         'charity or not-for-profit organisation',
    partnership:     'partnership',
    freelancer:      'freelancer or independent consultant'
};

function sentenceCase(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function cleanUrl(u) {
    u = (u || '').trim();
    if (u && !/^https?:\/\//i.test(u)) u = 'https://' + u;
    return u.replace(/\/$/, '');
}

function build(a) {
    a = a || {};
    var name = (a.business_name || '').trim();
    var url  = cleanUrl(a.site_url || '');
    var email = (a.contact_email || '').trim();
    var addr = (a.contact_address || '').trim();
    var typeLabel = TYPE_LABELS[a.business_type] || TYPE_LABELS['limited-company'];
    var data = Array.isArray(a.data) ? a.data.filter(function (d) { return DATA_LABELS[d]; }) : [];
    var tps  = Array.isArray(a.third_parties) ? a.third_parties.filter(function (t) { return t === 'none' || TP_LABELS[t]; }) : [];
    var cookies = a.cookies === 'yes';
    var cookieTypes = cookies && Array.isArray(a.cookie_types) ? a.cookie_types.filter(function (c) { return COOKIE_LABELS[c]; }) : [];
    var transfers = a.transfers === 'yes';
    var children = a.children === 'yes';
    var today = new Date();
    var updated = today.getDate() + ' ' + ['January','February','March','April','May','June','July','August','September','October','November','December'][today.getMonth()] + ' ' + today.getFullYear();

    var we = 'We'; // keep neutral tone
    var sections = [];

    function push(heading, paras) { sections.push({ heading: heading, paras: paras }); }

    // 1. Who we are
    var p1 = ['This privacy policy explains how ' + name + ' (' + we.toLowerCase() + ', "us", "our") collects and uses your personal data when you visit ' + url + ' or use our services.'];
    p1.push('We are a ' + typeLabel + ' based in the United Kingdom. We are the data controller for the personal data described in this policy, which means we decide how and why it is used.');
    p1.push('We take your privacy seriously and we handle your data in line with the UK General Data Protection Regulation (UK GDPR) and the Data Protection Act 2018.');
    if (addr) p1.push('Our registered or trading address is: ' + addr + '.');
    p1.push('If you have questions about this policy or your data, contact us at ' + email + '.');
    push('Who we are', p1);

    // 2. Data we collect
    var p2 = [];
    if (data.length) {
        p2.push('We collect the following personal data, depending on how you interact with us:');
        p2 = p2.concat(data.map(function (d) { return '• ' + sentenceCase(DATA_LABELS[d]) + '.'; }));
        p2.push('We only collect personal data that we genuinely need for the purposes described in this policy. We do not collect special category data (such as health, religion or biometric data) unless you choose to share it with us.');
    } else {
        p2.push('We do not actively collect personal data beyond the basic technical information your browser shares automatically (see the Cookies section below). If you contact us, we will hold the details you choose to send.');
    }
    push('The personal data we collect', p2);

    // 3. How and why we use your data (lawful bases derived from selections)
    var bases = [];
    var has = function (d) { return data.indexOf(d) !== -1; };
    if (has('enquiries') || has('payments') || has('accounts') || has('names') || has('emails') || has('phones') || has('addresses')) {
        bases.push('• Contract — where we need your data to provide a service you have asked for, for example responding to your enquiry or fulfilling your order.');
    }
    if (has('analytics') || has('enquiries')) {
        bases.push('• Legitimate interests — where using your data is reasonably necessary for our business, for example running, securing and improving our website. We always balance this against your rights.');
    }
    if (has('marketing_prefs') || cookies) {
        bases.push('• Consent — where we only use your data with your permission, for example sending marketing emails or setting non-essential cookies. You can withdraw consent at any time.');
    }
    if (has('payments')) {
        bases.push('• Legal obligation — where we must keep records by law, for example invoices and tax records.');
    }
    var p3 = ['We use your personal data for the following purposes:'];
    p3.push('• To respond to your enquiries and provide the services you request.');
    p3.push('• To operate, secure and improve our website.');
    if (has('marketing_prefs')) p3.push('• To send you marketing communications you have agreed to receive.');
    if (has('payments')) p3.push('• To take payments and keep the records the law requires us to keep.');
    p3.push('Under UK data protection law we must have a lawful basis for each use. Ours are:');
    p3 = p3.concat(bases.length ? bases : ['• Legitimate interests — to run and improve our website and respond to people who contact us.']);
    push('How and why we use your data', p3);

    // 4. Who we share data with
    var p4 = ['We do not sell your personal data. We share it only where necessary:'];
    var realTps = tps.filter(function (t) { return t !== 'none'; });
    if (realTps.length) {
        p4.push('With trusted service providers who process data on our behalf:');
        p4 = p4.concat(realTps.map(function (t) { return '• ' + TP_LABELS[t] + '.'; }));
        p4.push('These providers may only use your data to provide their service to us, and they must protect it.');
    } else {
        p4.push('We do not currently share your data with third-party service providers beyond what is needed to run our website.');
    }
    p4.push('We may also disclose your data where the law requires it — for example to HMRC, regulators, or law enforcement — or to protect our legal rights.');
    push('Who we share your data with', p4);

    // 5. Cookies
    var p5 = [];
    if (cookies) {
        p5.push('Our website uses cookies — small files stored on your device that help the site work and help us understand how it is used.');
        if (cookieTypes.length) {
            p5.push('The types of cookies we use are:');
            p5 = p5.concat(cookieTypes.map(function (c) { return '• ' + sentenceCase(COOKIE_LABELS[c]) + '.'; }));
        }
        p5.push('You can control cookies through your browser settings, and you can withdraw consent for non-essential cookies at any time. Blocking cookies may affect how parts of our site work.');
    } else {
        p5.push('Our website does not set its own cookies. Please note that embedded third-party content (such as videos or maps), if used, may set cookies governed by those providers\' own policies.');
    }
    push('Cookies', p5);

    // 6. Retention
    var retMap = {
        'active-only':  'We keep your personal data only for as long as you are an active customer or client, and delete it when the relationship ends (unless the law requires us to keep records longer).',
        'active-plus-6y':'We keep your personal data while you are an active customer or client. After that, we keep financial and transaction records for 6 years to meet HMRC requirements, then delete them.',
        'enquiries-12m':'We keep enquiry details for 12 months after our last contact with you, so we can follow up properly, then delete them.',
        'custom': (a.retention_custom || '').trim() || 'We keep your personal data only for as long as necessary for the purposes described in this policy, then delete it.'
    };
    var ret = retMap[a.retention] || retMap['active-only'];
    push('How long we keep your data', [ret, 'When data is no longer needed, we delete it or anonymise it so it can no longer identify you.']);

    // 7. Rights
    push('Your data protection rights', [
        'Under UK GDPR you have the right to:',
        '• Access — ask for a copy of the personal data we hold about you.',
        '• Rectification — ask us to correct inaccurate or incomplete data.',
        '• Erasure — ask us to delete your data in certain circumstances ("the right to be forgotten").',
        '• Restriction — ask us to limit how we use your data.',
        '• Data portability — receive your data in a usable format or have it transferred.',
        '• Object — object to our use of your data, including direct marketing.',
        '• Withdraw consent — where we rely on your consent, you can withdraw it at any time.',
        'To exercise any of these rights, email us at ' + email + '. We will respond within one month. You do not have to pay a fee.',
        'You also have the right to complain to the Information Commissioner\'s Office (ICO), the UK data protection regulator, at https://ico.org.uk or on 0303 123 1113. We would appreciate the chance to put things right first — please contact us before raising a complaint.'
    ]);

    // 8. Security
    push('Keeping your data secure', [
        'We use appropriate technical and organisational measures to protect your personal data, including secure hosting, encrypted connections (HTTPS) and access controls.',
        'No system is completely secure, but we work to keep your data safe and we limit access to people who need it to do their jobs.'
    ]);

    // 9. International transfers
    if (transfers) {
        push('International data transfers', [
            'Some of our service providers may process your data outside the UK. Where this happens, we make sure your data is protected — for example through the UK Extension to the EU-US Data Privacy Framework or standard contractual clauses approved for UK transfers.'
        ]);
    } else {
        push('Where your data is stored', [
            'We store and process your personal data in the United Kingdom.'
        ]);
    }

    // 10. Children
    if (children) {
        push('Children\'s privacy', [
            'Our services may be used by children under 13. Where we knowingly collect data from a child, we do so with the consent of a parent or guardian. If you believe a child has provided us with personal data without that consent, contact us at ' + email + ' and we will delete it promptly.'
        ]);
    } else {
        push('Children\'s privacy', [
            'Our website and services are not directed at children under 13, and we do not knowingly collect their personal data. If you believe we have done so by mistake, contact us at ' + email + ' and we will delete it promptly.'
        ]);
    }

    // 11. Changes
    push('Changes to this policy', [
        'We may update this policy from time to time — for example if the law changes or we add new services. The latest version will always be published at ' + url + ', with the "last updated" date shown below.'
    ]);

    // 12. Contact
    var pc = ['If you have any questions about this policy or how we handle your data, contact us:'];
    pc.push('• Email: ' + email);
    if (addr) pc.push('• Post: ' + addr);
    pc.push('• Website: ' + url);
    push('Contact us', pc);

    // Plaintext render
    var lines = [];
    lines.push('PRIVACY POLICY — ' + name.toUpperCase());
    lines.push('Last updated: ' + updated);
    lines.push('');
    sections.forEach(function (s, i) {
        lines.push((i + 1) + '. ' + s.heading.toUpperCase());
        lines.push('');
        s.paras.forEach(function (p) { lines.push(p); lines.push(''); });
    });
    lines.push('---');
    lines.push('Generated with the Altus Web Solutions free Privacy Policy Generator. This is a draft template, not legal advice. Have it reviewed by a solicitor before publishing.');

    return {
        title: 'Privacy Policy — ' + name,
        last_updated: updated,
        business_name: name,
        sections: sections,
        plaintext: lines.join('\n')
    };
}

return { build: build, DATA_LABELS: DATA_LABELS, TP_LABELS: TP_LABELS, COOKIE_LABELS: COOKIE_LABELS };
});
