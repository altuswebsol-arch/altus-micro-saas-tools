/* Altus Meta Description Checker — pure logic helpers.
 * No DOM here: unit-tested by test-harness.js, reused by generator.js.
 *
 * Pixel widths are ESTIMATES. They use true Arial glyph advance widths
 * (units per 1000 em) scaled by 0.85 — a calibration so that a typical
 * 155-character description lands near Google's observed ~920 px desktop
 * cut-off, matching the industry's "120–155 characters" guidance.
 * Google renders snippets dynamically and often rewrites them, so every
 * width in this tool is labelled an estimate.
 */
'use strict';

/* Arial advance widths, units per 1000 em (from the Arial metric tables). */
var GLYPHS = {
    ' ': 278, '!': 278, '"': 355, '#': 556, '$': 556, '%': 889, '&': 667,
    "'": 191, '(': 333, ')': 333, '*': 389, '+': 584, ',': 278, '-': 333,
    '.': 278, '/': 278,
    '0': 556, '1': 556, '2': 556, '3': 556, '4': 556, '5': 556,
    '6': 556, '7': 556, '8': 556, '9': 556,
    ':': 278, ';': 278, '<': 584, '=': 584, '>': 584, '?': 556, '@': 1015,
    'A': 667, 'B': 667, 'C': 722, 'D': 722, 'E': 667, 'F': 611, 'G': 778,
    'H': 722, 'I': 278, 'J': 500, 'K': 667, 'L': 556, 'M': 833, 'N': 722,
    'O': 778, 'P': 667, 'Q': 778, 'R': 722, 'S': 667, 'T': 611, 'U': 722,
    'V': 667, 'W': 944, 'X': 667, 'Y': 667, 'Z': 611,
    '[': 278, ']': 278, '^': 584, '_': 556, '`': 278,
    'a': 556, 'b': 556, 'c': 500, 'd': 556, 'e': 556, 'f': 278, 'g': 556,
    'h': 556, 'i': 222, 'j': 222, 'k': 500, 'l': 222, 'm': 833, 'n': 556,
    'o': 556, 'p': 556, 'q': 556, 'r': 333, 's': 500, 't': 278, 'u': 556,
    'v': 500, 'w': 722, 'x': 500, 'y': 500, 'z': 500,
    '{': 389, '|': 278, '}': 389, '~': 584, '£': 556, '€': 556, '…': 778
};
var DEFAULT_GLYPH = 556;
var CALIBRATION = 0.85; /* see header note */

var DESC_MAX_PX = 920;    /* Google desktop description cut-off (approx) */
var DESC_MOBILE_PX = 680; /* Google mobile description cut-off (approx) */
var TITLE_MAX_PX = 600;   /* Google desktop title cut-off (approx) */
var TITLE_FONT = 20;      /* Google renders titles ~20px */
var DESC_FONT = 14;       /* ...and descriptions ~14px */

var CTA_WORDS = [
    'buy', 'shop', 'order', 'book', 'call', 'contact', 'get', 'download',
    'try', 'start', 'learn', 'discover', 'sign up', 'signup', 'request',
    'quote', 'free', 'today', 'now', 'visit', 'explore', 'find out'
];
var ALLOWED_CAPS = ['UK', 'USA', 'UAE', 'SEO', 'PPC', 'LTD', 'FAQ', 'CTA', 'PDF', 'AI', 'VAT'];

function clean(s) {
    return (s || '').replace(/\s+/g, ' ').trim();
}

/* Estimated pixel width of text at the given font size. */
function estimateWidth(text, fontSize) {
    text = clean(text);
    fontSize = fontSize || DESC_FONT;
    var units = 0;
    for (var i = 0; i < text.length; i++) {
        var c = text.charAt(i);
        units += (c in GLYPHS) ? GLYPHS[c] : DEFAULT_GLYPH;
    }
    return Math.round(units * fontSize / 1000 * CALIBRATION);
}

/* Cut text to a pixel budget, ending at a word boundary with "...". */
function truncateToWidth(text, maxPx, fontSize) {
    text = clean(text);
    fontSize = fontSize || DESC_FONT;
    var units = 0, out = '', lastSpace = -1;
    for (var i = 0; i < text.length; i++) {
        var c = text.charAt(i);
        units += ((c in GLYPHS) ? GLYPHS[c] : DEFAULT_GLYPH) * fontSize / 1000 * CALIBRATION;
        out += c;
        if (c === ' ') lastSpace = out.length - 1;
        if (units > maxPx) {
            if (lastSpace > 10) return text.slice(0, lastSpace) + '...';
            return out.slice(0, Math.max(0, out.length - 1)) + '...';
        }
    }
    return text;
}

function fitsWidth(text, maxPx, fontSize) {
    return estimateWidth(text, fontSize) <= maxPx;
}

/* words of 4+ letters that are ALL CAPS and not in the allowed list */
function allCapsWords(text) {
    var found = [];
    var words = clean(text).split(' ');
    for (var i = 0; i < words.length; i++) {
        var w = words[i].replace(/^[^A-Za-z]+|[^A-Za-z]+$/g, '');
        if (w.length >= 4 && w === w.toUpperCase() && w !== w.toLowerCase()
                && ALLOWED_CAPS.indexOf(w) === -1 && found.indexOf(w) === -1) {
            found.push(w);
        }
    }
    return found;
}

function escapeRe(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function containsWord(haystack, needle) {
    if (!needle) return false;
    return new RegExp('\\b' + escapeRe(needle) + '\\b', 'i').test(haystack || '');
}

function ctaPresent(text) {
    text = clean(text);
    for (var i = 0; i < CTA_WORDS.length; i++) {
        if (containsWord(text, CTA_WORDS[i])) return CTA_WORDS[i];
    }
    return null;
}

/* ---- description analysis ------------------------------------------ */
function analyzeDescription(desc, title, keyword) {
    desc = clean(desc);
    title = clean(title);
    keyword = clean(keyword);
    var chars = desc.length;
    var px = estimateWidth(desc, DESC_FONT);
    var checks = [];

    function push(id, status, label, detail) {
        checks.push({ id: id, status: status, label: label, detail: detail });
    }

    if (!chars) {
        push('present', 'fail', 'Meta description present',
            'There is no meta description — Google will write one for you from page text, and it may not say what you want.');
        return { chars: 0, px: 0, checks: checks, truncated: false };
    }
    push('present', 'pass', 'Meta description present',
        chars + ' characters, about ' + px + ' px wide (estimate).');

    if (chars < 50) {
        push('length', 'warn', 'Length: very short',
            'Under 50 characters — Google often rewrites short descriptions. Aim for 120–155 characters.');
    } else if (chars <= 155) {
        push('length', 'pass', 'Length: good',
            chars + ' characters sits in the safe range (about 120–155).');
    } else {
        push('length', 'warn', 'Length: too long',
            chars + ' characters — Google shows roughly the first 155. Front-load the important words.');
    }

    if (px <= DESC_MAX_PX) {
        push('pixels', 'pass', 'Fits Google\u2019s width',
            'About ' + px + ' px — inside the ~920 px Google shows on desktop.');
    } else {
        var shown = truncateToWidth(desc, DESC_MAX_PX, DESC_FONT);
        push('pixels', 'warn', 'Will be cut off',
            'About ' + px + ' px — wider than Google\u2019s ~920 px, so visitors would see: \u201C' + shown + '\u201D');
    }

    if (keyword) {
        if (containsWord(desc, keyword)) {
            var pos = desc.toLowerCase().indexOf(keyword.toLowerCase());
            if (pos <= 120) {
                push('keyword', 'pass', 'Keyword included, near the start',
                    '\u201C' + keyword + '\u201D appears ' + (pos + 1) + ' characters in — good, in case the end is cut.');
            } else {
                push('keyword', 'warn', 'Keyword included, but buried',
                    '\u201C' + keyword + '\u201D appears ' + (pos + 1) + ' characters in — move it nearer the start.');
            }
        } else {
            push('keyword', 'warn', 'Keyword missing',
                '\u201C' + keyword + '\u201D does not appear — including the search term can help the snippet read as relevant.');
        }
    }

    if (title && desc.toLowerCase() === title.toLowerCase()) {
        push('unique', 'fail', 'Not a copy of the title',
            'The description is identical to the page title — it wastes the space and reads badly in results.');
    } else {
        push('unique', 'pass', 'Not a copy of the title', 'Description and title are different — good.');
    }

    var cta = ctaPresent(desc);
    if (cta) {
        push('cta', 'pass', 'Has a call to action', 'Includes \u201C' + cta + '\u201D — tells the searcher what to do next.');
    } else {
        push('cta', 'warn', 'No call to action',
            'No clear next step (like \u201Cbook\u201D, \u201Cshop\u201D, \u201Ccall\u201D, \u201Cget a quote\u201D). One usually lifts clicks.');
    }

    var caps = allCapsWords(desc);
    if (caps.length) {
        push('caps', 'fail', 'No SHOUTING',
            'All-caps words look spammy in results: ' + caps.join(', ') + '. Use normal sentence case.');
    } else {
        push('caps', 'pass', 'No SHOUTING', 'No all-caps words — reads naturally.');
    }

    if (/[.!?…:;]$/.test(desc)) {
        push('ending', 'pass', 'Ends cleanly', 'Finishes with punctuation — looks finished if it is cut slightly short.');
    } else {
        push('ending', 'warn', 'Ends mid-thought', 'No closing punctuation — add a full stop or a call to action at the end.');
    }

    return { chars: chars, px: px, checks: checks, truncated: px > DESC_MAX_PX };
}

/* ---- title analysis ------------------------------------------------- */
function analyzeTitle(title, keyword) {
    title = clean(title);
    keyword = clean(keyword);
    var chars = title.length;
    var px = estimateWidth(title, TITLE_FONT);
    var checks = [];

    function push(id, status, label, detail) {
        checks.push({ id: id, status: status, label: label, detail: detail });
    }

    if (!chars) {
        push('present', 'fail', 'Page title present', 'No title found — every page needs one.');
        return { chars: 0, px: 0, checks: checks };
    }
    push('present', 'pass', 'Page title present', chars + ' characters, about ' + px + ' px wide (estimate).');

    if (chars < 30) {
        push('length', 'warn', 'Title: short', 'Under 30 characters — you have room for a keyword or location.');
    } else if (px <= TITLE_MAX_PX) {
        push('length', 'pass', 'Title: good length', 'Fits inside Google\u2019s ~600 px — it should show in full.');
    } else {
        var shown = truncateToWidth(title, TITLE_MAX_PX, TITLE_FONT);
        push('length', 'warn', 'Title: will be cut off',
            'About ' + px + ' px — wider than Google\u2019s ~600 px, so visitors would see: \u201C' + shown + '\u201D');
    }

    if (keyword) {
        if (containsWord(title, keyword)) {
            push('keyword', 'pass', 'Keyword in title', '\u201C' + keyword + '\u201D is in the title — the strongest on-page signal you control.');
        } else {
            push('keyword', 'warn', 'Keyword missing from title', '\u201C' + keyword + '\u201D is not in the title — consider adding it near the front.');
        }
    }

    var caps = allCapsWords(title);
    if (caps.length) {
        push('caps', 'fail', 'No SHOUTING', 'All-caps words in the title: ' + caps.join(', ') + '.');
    } else {
        push('caps', 'pass', 'No SHOUTING', 'Title reads naturally.');
    }

    return { chars: chars, px: px, checks: checks };
}

/* ---- scoring --------------------------------------------------------- */
function scoreAll(descChecks, titleChecks) {
    var total = 0, got = 0;
    function add(checks, weight) {
        for (var i = 0; i < checks.length; i++) {
            var c = checks[i];
            if (c.id === 'present' && c.status === 'fail') continue; // empty input scores via the rest
            total += weight;
            if (c.status === 'pass') got += weight;
            else if (c.status === 'warn') got += weight * 0.5;
        }
    }
    add(descChecks, 2);
    add(titleChecks, 1);
    if (!total) return 0;
    return Math.round((got / total) * 100);
}

function gradeFor(score) {
    if (score >= 85) return { label: 'Excellent', cls: 'good' };
    if (score >= 65) return { label: 'Good', cls: 'good' };
    if (score >= 40) return { label: 'Needs work', cls: 'warn' };
    return { label: 'Poor', cls: 'bad' };
}

/* ---- report text (download + email) ---------------------------------- */
function buildReport(url, title, desc, keyword, score, grade, dChecks, tChecks) {
    var L = [];
    L.push('META DESCRIPTION CHECK \u2014 Altus Web Solutions');
    L.push('Free tool: https://altuswebsolutions.com/tools/meta-description-checker/');
    L.push('');
    if (url) L.push('Page: ' + url);
    L.push('Title: ' + (title || '(none)'));
    L.push('Meta description: ' + (desc || '(none)'));
    if (keyword) L.push('Keyword checked: ' + keyword);
    L.push('');
    L.push('SCORE: ' + score + '/100 \u2014 ' + grade);
    L.push('');
    L.push('META DESCRIPTION CHECKS:');
    dChecks.forEach(function (c) {
        L.push('[' + c.status.toUpperCase() + '] ' + c.label + ' \u2014 ' + c.detail);
    });
    L.push('');
    L.push('TITLE CHECKS:');
    tChecks.forEach(function (c) {
        L.push('[' + c.status.toUpperCase() + '] ' + c.label + ' \u2014 ' + c.detail);
    });
    L.push('');
    L.push('Note: pixel widths are estimates (Google renders snippets dynamically and');
    L.push('often rewrites descriptions). Length guidance: 120\u2013155 characters for the');
    L.push('description, under ~60 for the title.');
    L.push('');
    L.push('Want your whole site\u2019s SEO reviewed? Book a free 15-minute consultation:');
    L.push('https://altuswebsolutions.com/schedule-free-consultation/');
    return L.join('\n');
}

var MDC = {
    clean: clean,
    estimateWidth: estimateWidth,
    truncateToWidth: truncateToWidth,
    fitsWidth: fitsWidth,
    allCapsWords: allCapsWords,
    containsWord: containsWord,
    ctaPresent: ctaPresent,
    analyzeDescription: analyzeDescription,
    analyzeTitle: analyzeTitle,
    scoreAll: scoreAll,
    gradeFor: gradeFor,
    buildReport: buildReport,
    DESC_MAX_PX: DESC_MAX_PX,
    DESC_MOBILE_PX: DESC_MOBILE_PX,
    TITLE_MAX_PX: TITLE_MAX_PX,
    TITLE_FONT: TITLE_FONT,
    DESC_FONT: DESC_FONT
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = MDC;
} else if (typeof window !== 'undefined') {
    window.MDC = MDC;
}
