(function ($, STUDIP) {
    'use strict';

    STUDIP.Zoom = {

        init: function() {
            if ($('#create-coursedates').is(':checked')) {
                $('.manual-time').hide()
            }
            $('#create-coursedates').on('click', function() {
                $('.manual-time').hide()
            });
            $('#create-manual').on('click', function() {
                $('.manual-time').show()
            });
        }
    };

    STUDIP.ready(function () {
        STUDIP.Zoom.init();
        $(document).on('dialog-update', function() {
            STUDIP.Zoom.init();
        });
    });

}(jQuery, STUDIP));
