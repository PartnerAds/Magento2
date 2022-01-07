define([
    'uiComponent',
    'jquery'
], function (Component, $) {
    'use strict';

    return Component.extend({

        initialize: function (config) {

            $.ajax({
                url: config.url,
                data: {
                    'partnerId': config.partnerId,
                    'pacId': config.pacId
                },
                type: 'GET',
                global: true,
                contentType: 'application/json',
                cache: false
            });
        }
    });
});