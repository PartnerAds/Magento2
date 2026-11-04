/**
 * Partner-ads tracker (Partner_Module 2.4.0+).
 *
 * Loaded by Partner_Module::script.phtml as one external script on every storefront page, with the cookie endpoint in
 * its data-endpoint attribute. Plain ES5 without dependencies, so it runs on Luma, Breeze and Hyvä alike.
 *
 * The page HTML carries no request values, so it is safe in Varnish and the built-in full-page cache. The tracking
 * parameters are read here, in the browser, from location.search:
 *   - pa-partnerid  partner ID; without a valid one nothing is sent
 *   - pacid         click ID; sent only when valid (the server then replaces or deletes the pacid cookie)
 * The cookie endpoint validates both values again and sets the partner_id / pacid cookies.
 *
 * At most one request per page lifetime, also when a second copy of this tracker or the deprecated
 * Partner_Module/js/partner component runs on the same page (window.PartnerAdsTracked).
 */
(function (window, document) {
    'use strict';

    var GUARD = 'PartnerAdsTracked';
    var PARAM_PARTNER = 'pa-partnerid';
    var PARAM_PACID = 'pacid';

    // Keep identical to PARTNER_ID_PATTERN / PACID_PATTERN in Model/TrackingCookieManager.php
    var PARTNER_ID_PATTERN = /^[1-9][0-9]{0,9}$/;
    var PACID_PATTERN = /^[A-Za-z0-9._-]{1,255}$/;

    /**
     * Decodes one query-string component ("+" is a space); throws on malformed percent-encoding.
     *
     * @param {string} value
     * @return {string}
     */
    function decode(value) {
        return decodeURIComponent(value.replace(/\+/g, ' '));
    }

    /**
     * Collects every value given for each name in the query string. A name may occur only once: array-style keys
     * ("pacid[]", "pacid[x]") add a null entry, as do values that can't be decoded, so the value counts as invalid.
     *
     * @param {string} search location.search
     * @param {Array} names
     * @return {Object} name => array of values (string, or null for an invalid occurrence)
     */
    function readParams(search, names) {
        var found = {};
        var add;
        var pairs;
        var pair;
        var eq;
        var key;
        var value;
        var i;

        for (i = 0; i < names.length; i++) {
            found[names[i]] = [];
        }

        add = function (k, v) {
            var j;

            for (j = 0; j < names.length; j++) {
                if (k === names[j]) {
                    found[names[j]].push(v);
                } else if (k.indexOf(names[j] + '[') === 0) {
                    found[names[j]].push(null);
                }
            }
        };

        search = String(search || '');

        if (typeof window.URLSearchParams === 'function') {
            new window.URLSearchParams(search).forEach(function (v, k) {
                add(k, v);
            });

            return found;
        }

        pairs = search.replace(/^\?/, '').split('&');

        for (i = 0; i < pairs.length; i++) {
            pair = pairs[i];

            if (!pair) {
                continue;
            }

            eq = pair.indexOf('=');
            key = eq < 0 ? pair : pair.substring(0, eq);
            value = eq < 0 ? '' : pair.substring(eq + 1);

            try {
                key = decode(key);
            } catch (e) {
                value = null;
            }

            if (value !== null) {
                try {
                    value = decode(value);
                } catch (e) {
                    value = null;
                }
            }

            add(key, value);
        }

        return found;
    }

    /**
     * The value if the name occurred exactly once with a plain value matching the pattern, otherwise an empty string.
     *
     * @param {Array} values
     * @param {RegExp} pattern
     * @return {string}
     */
    function valid(values, pattern) {
        return values.length === 1 && typeof values[0] === 'string' && pattern.test(values[0]) ? values[0] : '';
    }

    /**
     * The cookie endpoint from this script element's data-endpoint attribute.
     *
     * @return {string}
     */
    function endpoint() {
        var script = document.currentScript;
        var scripts;
        var i;

        if (!script || !script.getAttribute('data-endpoint')) {
            script = null;
            scripts = document.getElementsByTagName('script');

            for (i = 0; i < scripts.length; i++) {
                if (scripts[i].getAttribute('data-endpoint') && /Partner_Module\/js\/tracker/.test(scripts[i].src)) {
                    script = scripts[i];
                    break;
                }
            }
        }

        return script ? script.getAttribute('data-endpoint') || '' : '';
    }

    /**
     * One GET request; fetch where available, otherwise XMLHttpRequest. Errors are ignored.
     *
     * @param {string} url
     */
    function send(url) {
        var xhr;

        if (typeof window.fetch === 'function') {
            try {
                window.fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true
                }).then(null, function () {});

                return;
            } catch (e) {
                // fall through to XMLHttpRequest
            }
        }

        xhr = new window.XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.send();
    }

    try {
        (function () {
            var params;
            var partnerId;
            var pacId;
            var url;

            if (window[GUARD]) {
                return;
            }

            params = readParams(window.location.search, [PARAM_PARTNER, PARAM_PACID]);
            partnerId = valid(params[PARAM_PARTNER], PARTNER_ID_PATTERN);

            if (!partnerId) {
                return;
            }

            pacId = valid(params[PARAM_PACID], PACID_PATTERN);
            url = endpoint();

            if (!url) {
                return;
            }

            window[GUARD] = true;
            send(url + (url.indexOf('?') < 0 ? '?' : '&')
                + 'partnerId=' + encodeURIComponent(partnerId)
                + (pacId ? '&pacId=' + encodeURIComponent(pacId) : '')
                + '&_=' + new Date().getTime());
        }());
    } catch (e) {
        // never break the page
    }
}(window, document));
