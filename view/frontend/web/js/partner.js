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
        config = config || {};

        var partnerIdFromUrl = getQueryParam('pa-partnerid');
        var pacIdFromUrl = getQueryParam('pacid');
        var partnerId = partnerIdFromUrl || config.partnerId || '';
        var pacId = pacIdFromUrl || config.pacId || '';

        if (!partnerId && !pacId) {
            return;
        }

        $.ajax({
            url: config.url,
            data: {
                'partnerId': partnerId,
                'pacId': pacId,
                '_': Date.now()
            },
            type: 'GET',
            global: true,
            contentType: 'application/json',
            cache: false
        });
    };
});
