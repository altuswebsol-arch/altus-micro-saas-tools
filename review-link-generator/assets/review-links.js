/*
 * Altus Review Link Generator — pure link logic (no DOM).
 * Shared between the browser UI and the Node test harness.
 *
 * Google's review links, as documented by Google:
 *  - Universal review dialog link:  https://search.google.com/local/writereview?placeid=<PLACE_ID>
 *    (opens the "write a review" dialog for the place directly)
 *  - Universal Maps URL (fallback): https://www.google.com/maps/search/?api=1&query_place_id=<PLACE_ID>
 *  - g.page short links are issued to the business owner inside their
 *    Google Business Profile ("share review form"); they look like
 *    https://g.page/r/<shortcode>/review
 */
(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = factory();
    } else {
        root.AltusRLG = factory();
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    var REVIEW_BASE = 'https://search.google.com/local/writereview?placeid=';
    var MAPS_BASE = 'https://www.google.com/maps/search/?api=1';

    /** Trim and normalise user input. */
    function clean(s) {
        return (s === undefined || s === null) ? '' : String(s).trim();
    }

    /**
     * Google Place IDs are opaque alphanumeric tokens, usually 27 chars,
     * e.g. "ChIJN1t_tDeuEmsRUsoyG83frY4". We accept 10+ chars of
     * [A-Za-z0-9_-] so we never reject a real ID, while still catching
     * pasted business names, URLs or empty strings.
     */
    function isValidPlaceId(s) {
        return /^[A-Za-z0-9_-]{10,}$/.test(clean(s));
    }

    /** Detect a pasted Place ID even when the user pastes a full writereview URL. */
    function extractPlaceId(s) {
        var t = clean(s);
        var m = t.match(/[?&]placeid=([A-Za-z0-9_-]+)/i);
        if (m) return m[1];
        return t;
    }

    /** Universal direct-review link. Throws if the Place ID is invalid. */
    function buildReviewLink(placeId) {
        var id = extractPlaceId(placeId);
        if (!isValidPlaceId(id)) {
            throw new Error('That does not look like a Google Place ID.');
        }
        return REVIEW_BASE + encodeURIComponent(id);
    }

    /** Universal Maps fallback link. */
    function buildMapsLink(placeId, query) {
        var id = extractPlaceId(placeId);
        if (!isValidPlaceId(id)) {
            throw new Error('That does not look like a Google Place ID.');
        }
        var url = MAPS_BASE + '&query_place_id=' + encodeURIComponent(id);
        var q = clean(query);
        if (q) url += '&query=' + encodeURIComponent(q);
        return url;
    }

    /**
     * Accept a business's existing g.page review short link, e.g.
     * https://g.page/r/AbCdEfGhIjK/review  (or without /review),
     * and normalise it to the canonical form ending in /review.
     * Returns null if the input is not a g.page review link.
     */
    function parseGPage(url) {
        var m = clean(url).match(/^https?:\/\/(www\.)?g\.page\/r\/([A-Za-z0-9_-]+)\/?(review\/?)?$/i);
        if (!m) return null;
        return 'https://g.page/r/' + m[2] + '/review';
    }

    /** True if the string is a full URL we are willing to QR-encode. */
    function isHttpUrl(s) {
        return /^https:\/\/[^\s/$.?#].[^\s]*$/i.test(clean(s));
    }

    /**
     * Decide what the visitor pasted: a Place ID (build the review link),
     * a g.page short link (normalise it), or junk (return an error).
     */
    function resolveInput(raw, businessName) {
        var t = clean(raw);
        if (!t) return { ok: false, error: 'Please paste your Google Place ID or review link.' };
        var g = parseGPage(t);
        if (g) {
            return {
                ok: true,
                mode: 'gpage',
                reviewLink: g,
                mapsLink: '',
                note: 'This is your business\u2019s own Google short review link.'
            };
        }
        var id = extractPlaceId(t);
        if (!isValidPlaceId(id)) {
            return {
                ok: false,
                error: 'That does not look like a Google Place ID or a Google review link. ' +
                       'Use the 60-second finder below to get your Place ID.'
            };
        }
        return {
            ok: true,
            mode: 'placeid',
            reviewLink: REVIEW_BASE + encodeURIComponent(id),
            mapsLink: buildMapsLink(id, businessName),
            note: ''
        };
    }

    return {
        clean: clean,
        isValidPlaceId: isValidPlaceId,
        extractPlaceId: extractPlaceId,
        buildReviewLink: buildReviewLink,
        buildMapsLink: buildMapsLink,
        parseGPage: parseGPage,
        isHttpUrl: isHttpUrl,
        resolveInput: resolveInput
    };
});
