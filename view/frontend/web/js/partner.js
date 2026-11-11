/**
 * @deprecated since 2.4.0. Partner_Module::script.phtml loads Partner_Module/js/tracker.js instead, which works
 * without RequireJS. This component is kept for themes that still override script.phtml with the 2.3.x template
 * (text/x-magento-init with "Partner_Module/js/partner"). Remove such overrides after upgrading.
 *
 * Same behaviour as tracker.js: the tracking parameters are read only from the URL (location.search) and validated
 * with the server's patterns. The partnerId / pacId values of the init config are ignored since 2.4.0 (a page with
 * values printed into it must never be able to set cookies, e.g. when a cache serves it to other visitors); only its
 * "url" is used. One request per page lifetime (window.PartnerAdsTracked, shared with tracker.js), sent once more
 * after a network error, HTTP 429 or 5xx. No jQuery: the component runs wherever RequireJS does.
 */
define([], function () {
    'use strict';

    var GUARD = 'PartnerAdsTracked';
    var PARAM_PARTNER = 'pa-partnerid';
    var PARAM_PACID = 'pacid';

    // Keep identical to PARTNER_ID_PATTERN / PACID_PATTERN in Model/TrackingCookieManager.php
    var PARTNER_ID_PATTERN = /^[1-9][0-9]{0,9}$/;
    var PACID_PATTERN = /^[A-Za-z0-9._-]{1,255}$/;

    // FE-04: one more request after a network error, HTTP 429 or 5xx, about one second later
    var MAX_ATTEMPTS = 2;
    var RETRY_DELAY = 1000;

    function decode(value) {
        return decodeURIComponent(value.replace(/\+/g, ' '));
    }

    /**
     * Every value given for each name in the query string; array-style keys and undecodable values add null.
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

    function valid(values, pattern) {
        return values.length === 1 && typeof values[0] === 'string' && pattern.test(values[0]) ? values[0] : '';
    }

    /**
     * Whether a cookie request should be sent once more: a network error (status 0), HTTP 429 or 5xx.
     *
     * @param {number} status
     * @return {boolean}
     */
    function retryable(status) {
        return !status || status === 429 || status >= 500;
    }

    /**
     * GET the cookie endpoint; fetch where available, otherwise XMLHttpRequest. Each attempt gets a fresh "_"
     * timestamp. After a network error, HTTP 429 or 5xx the request is sent once more after RETRY_DELAY ms, so there
     * are at most MAX_ATTEMPTS requests. Errors are ignored.
     *
     * @param {string} url endpoint with partnerId / pacId, without "_"
     * @param {number} attempt 1 for the first request
     */
    function send(url, attempt) {
        var xhr;
        var full = url + '&_=' + new Date().getTime();
        var done = function (status) {
            if (attempt < MAX_ATTEMPTS && retryable(status)) {
                window.setTimeout(function () {
                    try {
                        send(url, attempt + 1);
                    } catch (e) {
                        // never break the page
                    }
                }, RETRY_DELAY);
            }
        };

        if (typeof window.fetch === 'function') {
            try {
                window.fetch(full, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true
                }).then(function (response) {
                    done(response && response.status);
                }, function () {
                    done(0);
                }).then(null, function () {});

                return;
            } catch (e) {
                // fall through to XMLHttpRequest
            }
        }

        xhr = new window.XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                xhr.onreadystatechange = null;
                done(xhr.status);
            }
        };
        xhr.open('GET', full, true);
        xhr.send();
    }

    /**
     * @param {Object} config x-magento-init config of the 2.3.x template; only "url" is used
     */
    return function (config) {
        var params;
        var partnerId;
        var pacId;
        var url;

        try {
            config = config || {};

            if (window[GUARD]) {
                return;
            }

            params = readParams(window.location.search, [PARAM_PARTNER, PARAM_PACID]);
            partnerId = valid(params[PARAM_PARTNER], PARTNER_ID_PATTERN);
            pacId = valid(params[PARAM_PACID], PACID_PATTERN);
            url = typeof config.url === 'string' ? config.url : '';

            if (!partnerId || !url) {
                return;
            }

            window[GUARD] = true;
            send(url + (url.indexOf('?') < 0 ? '?' : '&')
                + 'partnerId=' + encodeURIComponent(partnerId)
                + (pacId ? '&pacId=' + encodeURIComponent(pacId) : ''), 1);
        } catch (e) {
            // never break the page
        }
    };
});
