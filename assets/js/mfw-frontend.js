/**
 * MFW Frontend JavaScript
 */

(function($) {
    'use strict';

    const MFWFrontend = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.mfw-generate-button').on('click', this.handleGeneration);
            $('.mfw-refresh-content').on('click', this.refreshContent);
        },

        handleGeneration: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $container = $button.closest('.mfw-content-container');

            $button.prop('disabled', true);
            $container.find('.mfw-loading').show();

            $.ajax({
                url: mfw_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'mfw_generate_content',
                    nonce: mfw_frontend.nonce,
                    prompt: $container.find('.mfw-prompt').val()
                },
                success: function(response) {
                    if (response.success) {
                        $container.find('.mfw-generated-content').html(response.data);
                    } else {
                        $container.find('.mfw-error').text(response.data.message).show();
                    }
                },
                error: function() {
                    $container.find('.mfw-error').text('An error occurred').show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $container.find('.mfw-loading').hide();
                }
            });
        },

        refreshContent: function(e) {
            e.preventDefault();
            // Refresh content logic
        }
    };

    $(document).ready(function() {
        MFWFrontend.init();
    });

})(jQuery);