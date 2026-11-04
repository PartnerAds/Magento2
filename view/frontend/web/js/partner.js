/**
 * @deprecated since 2.4.0. Partner_Module::script.phtml loads Partner_Module/js/tracker.js instead, which works
 * without RequireJS. This component is kept for themes that still override script.phtml with the 2.3.x template
 * (text/x-magento-init with "Partner_Module/js/partner"). Remove such overrides after upgrading.
 *
 * Same behaviour as tracker.js: the tracking parameters from the URL first (they are missing from the config on
 * full-page-cached pages), the config values only when the URL has neither; both values validated with the server's
 * patterns; at most one request per page lifetime (window.PartnerAdsTracked, shared with tracker.js).
 * No jQuery: a plain request is enough, and the component then runs wherever RequireJS does.
 */
define([], function () {
    'use strict';

    var GUARD = 'PartnerAdsTracked';
    var PARAM_PARTNER = 'pa-partnerid';
    var PARAM_PACID = 'pacid';

    // Keep identical to PARTNER_ID_PATTERN / PACID_PATTERN in Model/TrackingCookieManager.php
    var PARTNER_ID_PATTERN = /^[1-9][0-9]{0,9}$/;
    var PACID_PATTERN = /^[A-Za-z0-9._-]{1,255}$/;

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

            if (params[PARAM_PARTNER].length || params[PARAM_PACID].length) {
                partnerId = valid(params[PARAM_PARTNER], PARTNER_ID_PATTERN);
                pacId = valid(params[PARAM_PACID], PACID_PATTERN);
            } else {
                partnerId = valid([config.partnerId], PARTNER_ID_PATTERN);
                pacId = valid([config.pacId], PACID_PATTERN);
            }

            url = typeof config.url === 'string' ? config.url : '';

            if (!partnerId || !url) {
                return;
            }

            window[GUARD] = true;
            send(url + (url.indexOf('?') < 0 ? '?' : '&')
                + 'partnerId=' + encodeURIComponent(partnerId)
                + (pacId ? '&pacId=' + encodeURIComponent(pacId) : '')
                + '&_=' + new Date().getTime());
        } catch (e) {
            // never break the page
        }
    };
});
