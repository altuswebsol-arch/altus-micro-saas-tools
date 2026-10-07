#!/usr/bin/env node
/* Test harness for policy-builder.js — run with `node test-harness.js`.
 * Verifies: structure, substitution of real answers, lawful-basis mapping,
 * no invented claims, branch coverage (cookies yes/no, transfers, children,
 * retention variants), and JS syntax of the UI file. */
'use strict';

const APPG = require('./assets/policy-builder.js');
const fs = require('fs');

let failures = 0;
function ok(cond, msg) {
    if (cond) { console.log('  PASS  ' + msg); }
    else { console.log('  FAIL  ' + msg); failures++; }
}

function baseAnswers(overrides) {
    return Object.assign({
        business_name: 'Springfield Plumbing Ltd',
        business_type: 'limited-company',
        site_url: 'https://springfieldplumbing.co.uk',
        contact_email: 'hello@springfieldplumbing.co.uk',
        contact_address: '12 High Street, Leeds LS1 4DY',
        data: ['names', 'emails', 'phones', 'enquiries', 'analytics', 'marketing_prefs', 'payments'],
        third_parties: ['google-analytics', 'payment-processors', 'hosting'],
        cookies: 'yes',
        cookie_types: ['essential', 'analytics'],
        transfers: 'yes',
        retention: 'active-plus-6y',
        retention_custom: '',
        children: 'no'
    }, overrides || {});
}

console.log('== Full fixture ==');
let g = APPG.build(baseAnswers());
ok(g.title === 'Privacy Policy — Springfield Plumbing Ltd', 'title uses business name');
ok(g.sections.length === 12, '12 sections generated (got ' + g.sections.length + ')');
ok(!/undefined|null|\[object/.test(g.plaintext), 'no undefined/null placeholders in text');
ok(g.plaintext.includes('Springfield Plumbing Ltd'), 'business name appears');
ok(g.plaintext.includes('hello@springfieldplumbing.co.uk'), 'contact email appears');
ok(g.plaintext.includes('https://springfieldplumbing.co.uk'), 'site url appears');
ok(g.plaintext.includes('12 High Street, Leeds LS1 4DY'), 'address appears');
ok(g.plaintext.includes('Google Analytics'), 'third party appears');
ok(g.plaintext.includes('payment processors'), 'payment processor appears');
ok(g.plaintext.includes('UK General Data Protection Regulation'), 'UK GDPR referenced');
ok(g.plaintext.includes('ico.org.uk'), 'ICO complaint route referenced');
ok(g.plaintext.includes('not legal advice'), 'not-legal-advice disclaimer present');
ok(/Contract —/.test(g.plaintext), 'contract lawful basis present');
ok(/Legitimate interests —/.test(g.plaintext), 'legitimate interests basis present');
ok(/Consent —/.test(g.plaintext), 'consent basis present');
ok(/Legal obligation —/.test(g.plaintext), 'legal obligation basis present');
ok(/6 years/.test(g.plaintext), 'retention variant applied');
ok(/outside the UK/.test(g.plaintext), 'international transfers section present');
ok(/not directed at children under 13/.test(g.plaintext), 'children-no branch present');
ok(g.plaintext.length > 4000, 'policy is substantial (' + g.plaintext.length + ' chars)');

console.log('== Cookies=no branch ==');
g = APPG.build(baseAnswers({ cookies: 'no' }));
ok(!/analytics cookies/i.test(g.plaintext), 'no analytics cookie claim when cookies=no');
ok(/does not set its own cookies/i.test(g.plaintext), 'cookies-no wording present');

console.log('== Minimal fixture (no data, no third parties) ==');
g = APPG.build(baseAnswers({ data: [], third_parties: ['none'], cookies: 'no', transfers: 'no', retention: 'custom', retention_custom: 'We keep emails for 6 months.', contact_address: '', children: 'yes' }));
ok(/do not actively collect personal data/i.test(g.plaintext), 'no-data branch present');
ok(/do not currently share your data with third-party service providers/i.test(g.plaintext), 'no-third-party branch present');
ok(/store and process your personal data in the United Kingdom/i.test(g.plaintext), 'transfers-no branch present');
ok(/We keep emails for 6 months/.test(g.plaintext), 'custom retention used verbatim');
ok(!/12 High Street/.test(g.plaintext), 'empty address omitted, no residue');
ok(/parent or guardian/i.test(g.plaintext), 'children-yes branch present');

console.log('== URL normalisation ==');
g = APPG.build(baseAnswers({ site_url: 'example.co.uk/' }));
ok(g.plaintext.includes('https://example.co.uk'), 'bare domain normalised to https, trailing slash trimmed');

console.log('== UI file syntax ==');
try {
    new (require('vm').Script)(fs.readFileSync('./assets/generator.js', 'utf8'));
    ok(true, 'generator.js parses');
} catch (e) { ok(false, 'generator.js parses: ' + e.message); }

console.log(failures === 0 ? '\nALL TESTS PASS' : '\n' + failures + ' TEST(S) FAILED');
process.exit(failures === 0 ? 0 : 1);
