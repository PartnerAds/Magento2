define([
    'jquery'
], function ($) {
    'use strict';

    function decode(value) {
        try {
            return decodeURIComponent((value || '').replace(/\+/g, ' '));
        } catch (e) {
            return value;
        }
    }

    function parseSearchLegacy(search) {
        var result = {};
        var query = search || '';
        var pairs;
        var i;
        var pair;
        var key;
        var value;

        if (!query || query.length < 2) {
            return result;
        }

        pairs = query.substring(1).split('&');

        for (i = 0; i < pairs.length; i++) {
            if (!pairs[i]) {
                continue;
            }

            pair = pairs[i].split('=');
            key = decode(pair[0] || '');
            value = decode(pair.length > 1 ? pair.slice(1).join('=') : '');

            if (key) {
                result[key] = value;
            }
        }

        return result;
    }

    function getQueryParam(name) {
        var params;

        if (window.URLSearchParams) {
            params = new URLSearchParams(window.location.search || '');
            return params.has(name) ? params.get(name) : '';
        }

        params = parseSearchLegacy(window.location.search || '');

        return params[name] || '';
    }

    return function (config) {
        var partnerIdFromUrl = getQueryParam('pa-partnerid');
        var pacIdFromUrl = getQueryParam('pacid');
        var hasServerValues = !!(config.partnerId || config.pacId);
        var partnerId = hasServerValues ? (config.partnerId || '') : (partnerIdFromUrl || '');
        var pacId = hasServerValues ? (config.pacId || '') : (pacIdFromUrl || '');

        if (!partnerId && !pacId) {
            return;
        }

        $.ajax({
            url: config.url,
            data: {
                'partnerId': partnerId,
                'pacId': pacId
            },
            type: 'GET',
            global: true,
            contentType: 'application/json',
            cache: false
        });
    };
});
