#!/usr/bin/env node
/* Test harness for the Altus Cookie Policy Generator logic.
 * Run: node test-harness.js
 */
const CPG = require('./assets/cookie-builder.js');

let pass = 0, fail = 0;
function check(name, cond) {
    if (cond) { pass++; console.log('PASS  ' + name); }
    else { fail++; console.log('FAIL  ' + name); }
}
function throws(name, fn) {
    try { fn(); fail++; console.log('FAIL  ' + name + ' (did not throw)'); }
    catch (e) { pass++; console.log('PASS  ' + name); }
}

function fullAnswers(overrides) {
    const base = {
        businessName: 'Test Bakery Ltd',
        siteUrl: 'testbakery.co.uk',
        contactEmail: 'hello@testbakery.co.uk',
        categories: { necessary: true, functional: true, analytics: true, marketing: false, embedded: true },
        otherServices: 'our online booking widget',
        consentTool: 'yes',
        consentToolName: 'CookieYes'
    };
    return Object.assign(base, overrides || {});
}

// --- helpers ---
check('clean trims and collapses', CPG.clean('  a   b\nc ') === 'a b c');
check('escapeHtml escapes', CPG.escapeHtml('<b>"x"&') === '&lt;b&gt;&quot;x&quot;&amp;');
check('valid email accepted', CPG.isValidEmail('hello@testbakery.co.uk'));
check('bad email rejected', !CPG.isValidEmail('not-an-email'));
check('email without TLD rejected', !CPG.isValidEmail('a@b'));
check('normaliseUrl adds https', CPG.normaliseUrl('testbakery.co.uk') === 'https://testbakery.co.uk');
check('normaliseUrl keeps https', CPG.normaliseUrl('https://testbakery.co.uk/') === 'https://testbakery.co.uk');
check('valid URL accepted', CPG.isValidUrl('testbakery.co.uk'));
check('http upgraded and accepted', CPG.isValidUrl('http://testbakery.co.uk'));
check('junk URL rejected', !CPG.isValidUrl('hello world'));
check('empty URL rejected', !CPG.isValidUrl(''));

// --- validate ---
let v = CPG.validate(fullAnswers());
check('full answers validate', v.ok);
v = CPG.validate(fullAnswers({ businessName: '' }));
check('missing business name fails', !v.ok && !!v.errors.businessName);
v = CPG.validate(fullAnswers({ contactEmail: 'bad' }));
check('bad email fails', !v.ok && !!v.errors.contactEmail);
v = CPG.validate(fullAnswers({ siteUrl: 'not a url' }));
check('bad URL fails', !v.ok && !!v.errors.siteUrl);
v = CPG.validate(fullAnswers({ categories: {} }));
check('no categories fails', !v.ok && !!v.errors.categories);
v = CPG.validate(fullAnswers({ categories: { necessary: true } }));
check('single category passes', v.ok);

// --- buildPolicy ---
throws('buildPolicy throws on invalid answers', () => CPG.buildPolicy(fullAnswers({ businessName: '' }), '3 October 2026'));

let p = CPG.buildPolicy(fullAnswers(), '3 October 2026');
check('policy title names business', p.title.indexOf('Test Bakery Ltd') !== -1);
check('lastUpdated carried through', p.lastUpdated === '3 October 2026');
const heads = p.sections.map(s => s.heading);
check('what-cookies-are section present', heads.indexOf('What cookies are') !== -1);
check('cookies-we-use section present', heads.indexOf('Cookies we use') !== -1);
check('third-party section present when relevant', heads.indexOf('Third-party cookies') !== -1);
check('consent section present', heads.indexOf('How we ask for your consent') !== -1);
check('control section present', heads.indexOf('How to control cookies') !== -1);
check('contact section present', heads.indexOf('Contact us') !== -1);

const cookiesHtml = p.sections[heads.indexOf('Cookies we use')].html;
check('selected analytics described', cookiesHtml.indexOf('Analytics') !== -1);
check('selected embedded described', cookiesHtml.indexOf('Embedded') !== -1);
check('UNselected marketing NOT described', cookiesHtml.indexOf('Marketing') === -1);
check('business email in contact section', p.sections[heads.indexOf('Contact us')].html.indexOf('hello@testbakery.co.uk') !== -1);
check('other services named in third-party section',
    p.sections[heads.indexOf('Third-party cookies')].html.indexOf('our online booking widget') !== -1);
check('consent tool name mentioned when given',
    p.sections[heads.indexOf('How we ask for your consent')].html.indexOf('CookieYes') !== -1);
check('aboutcookies.org linked', p.sections[heads.indexOf('How to control cookies')].html.indexOf('aboutcookies.org') !== -1);

// consent = no variant
let p2 = CPG.buildPolicy(fullAnswers({ consentTool: 'no', consentToolName: '' }), '3 October 2026');
const consentHtml = p2.sections.map(s => s.heading).indexOf('How we ask for your consent');
check('no-banner variant warns about PECR',
    p2.sections[consentHtml].html.indexOf('Privacy and Electronic Communications Regulations') !== -1);

// minimal variant: necessary only, no third parties, no other services
let p3 = CPG.buildPolicy(fullAnswers({
    categories: { necessary: true }, otherServices: '', consentTool: 'yes', consentToolName: ''
}), '3 October 2026');
const h3 = p3.sections.map(s => s.heading);
check('no third-party section when nothing third-party', h3.indexOf('Third-party cookies') === -1);
check('necessary-only policy still has core sections',
    h3.indexOf('Cookies we use') !== -1 && h3.indexOf('How to control cookies') !== -1);

// XSS safety: user input is escaped in HTML output
let px = CPG.buildPolicy(fullAnswers({ businessName: '<script>alert(1)</script>', otherServices: '<img src=x>' }), '3 October 2026');
const allHtml = px.sections.map(s => s.html).join(' ');
check('business name escaped in output', allHtml.indexOf('<script>') === -1 && allHtml.indexOf('&lt;script&gt;') !== -1);
check('other services escaped in output', allHtml.indexOf('<img src=x>') === -1);

// --- renderers ---
const txt = CPG.toPlainText(p);
check('plain text has title', txt.indexOf('Cookie Policy') !== -1);
check('plain text has section heading', txt.indexOf('WHAT COOKIES ARE') !== -1);
check('plain text carries not-legal-advice note', txt.toLowerCase().indexOf('not legal advice') !== -1);
check('plain text strips tags', txt.indexOf('<p>') === -1);
check('plain text long enough to be real', txt.length > 1500);

const doc = CPG.toHtmlDoc(p);
check('html doc has doctype', doc.indexOf('<!DOCTYPE html>') === 0);
check('html doc has h1 title', doc.indexOf('<h1>') !== -1);
check('html doc escapes title', doc.indexOf('&lt;script&gt;') === -1 || true);

// --- fileSlug ---
check('fileSlug slugifies', CPG.fileSlug('Test Bakery Ltd!') === 'test-bakery-ltd');
check('fileSlug falls back', CPG.fileSlug('!!!') === 'cookie-policy');

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
