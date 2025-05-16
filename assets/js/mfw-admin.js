/**
 * MFW Admin JavaScript
 * Handles admin interface interactions and AJAX requests
 */

(function($) {
    'use strict';

    const MFWAdmin = {
        settings: {
            ajaxUrl: mfwAdmin.ajaxUrl,
            nonce: mfwAdmin.nonce
        },

        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.setupValidation();
        },

        bindEvents: function() {
            // Content generation form
            $('#mfw-generate-content').on('submit', this.handleContentGeneration);

            // Template management
            $('#mfw-save-template').on('click', this.handleTemplateSave);
            $('.mfw-delete-template').on('click', this.handleTemplateDelete);

            // Settings form
            $('#mfw-settings-form').on('submit', this.handleSettingsSave);

            // Analytics refresh
            $('#mfw-refresh-analytics').on('click', this.refreshAnalytics);

            // Real-time preview
            $('#content_prompt').on('input', this.updatePreview);
            $('#content_type').on('change', this.updateOptions);
        },

        initializeComponents: function() {
            // Initialize tooltips
            $('.mfw-tooltip').tooltip();

            // Initialize tabs
            $('.mfw-tabs').tabs();

            // Initialize select2 dropdowns
            $('.mfw-select2').select2({
                theme: 'admin',
                width: '100%'
            });

            // Initialize CodeMirror editors
            $('.mfw-code-editor').each(function() {
                CodeMirror.fromTextArea(this, {
                    mode: 'xml',
                    theme: 'monokai',
                    lineNumbers: true,
                    autoCloseTags: true,
                    matchBrackets: true
                });
            });

            // Initialize date pickers
            $('.mfw-date-picker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        },

        setupValidation: function() {
            // Form validation rules
            $('.mfw-form').validate({
                rules: {
                    content_prompt: {
                        required: true,
                        minlength: 10
                    },
                    content_type: 'required',
                    content_tone: 'required'
                },
                messages: {
                    content_prompt: {
                        required: 'Please enter a prompt',
                        minlength: 'Prompt must be at least 10 characters'
                    },
                    content_type: 'Please select a content type',
                    content_tone: 'Please select a tone'
                },
                errorClass: 'mfw-error',
                validClass: 'mfw-valid'
            });
        },

        handleContentGeneration: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            const $result = $('#mfw-generation-result');

            if (!$form.valid()) {
                return;
            }

            $submit.prop('disabled', true).text('Generating...');
            $result.html('<div class="mfw-loader"></div>');

            $.ajax({
                url: MFWAdmin.settings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mfw_generate_content',
                    nonce: MFWAdmin.settings.nonce,
                    data: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        MFWAdmin.displayContent(response.data);
                    } else {
                        MFWAdmin.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    MFWAdmin.showError('An error occurred while generating content');
                },
                complete: function() {
                    $submit.prop('disabled', false).text('Generate Content');
                }
            });
        },

        displayContent: function(content) {
            const $result = $('#mfw-generation-result');
            $result.html(`
                <div class="mfw-content-preview">
                    <div class="mfw-content-actions">
                        <button class="button mfw-copy-content">Copy</button>
                        <button class="button mfw-edit-content">Edit</button>
                        <button class="button mfw-save-template">Save as Template</button>
                    </div>
                    <div class="mfw-content-body">${content}</div>
                </div>
            `);

            // Initialize content actions
            this.initializeContentActions();
        },

        initializeContentActions: function() {
            $('.mfw-copy-content').on('click', function() {
                const content = $('.mfw-content-body').text();
                navigator.clipboard.writeText(content).then(function() {
                    MFWAdmin.showMessage('Content copied to clipboard');
                }).catch(function() {
                    MFWAdmin.showError('Failed to copy content');
                });
            });

            $('.mfw-edit-content').on('click', function() {
                const $body = $('.mfw-content-body');
                const content = $body.text();
                $body.html(`
                    <textarea class="mfw-content-editor">${content}</textarea>
                    <button class="button mfw-save-edits">Save</button>
                `);
            });

            $('.mfw-save-template').on('click', function() {
                MFWAdmin.showTemplateDialog();
            });
        },

        showTemplateDialog: function() {
            const content = $('.mfw-content-body').text();
            const dialog = $(`
                <div class="mfw-dialog">
                    <h3>Save as Template</h3>
                    <div class="mfw-form-group">
                        <label>Template Name</label>
                        <input type="text" name="template_name" required>
                    </div>
                    <div class="mfw-form-group">
                        <label>Description</label>
                        <textarea name="template_description"></textarea>
                    </div>
                    <div class="mfw-dialog-actions">
                        <button class="button mfw-cancel">Cancel</button>
                        <button class="button button-primary mfw-save">Save</button>
                    </div>
                </div>
            `).dialog({
                modal: true,
                width: 500,
                close: function() {
                    $(this).dialog('destroy').remove();
                }
            });

            dialog.find('.mfw-save').on('click', function() {
                MFWAdmin.saveTemplate({
                    name: dialog.find('[name="template_name"]').val(),
                    description: dialog.find('[name="template_description"]').val(),
                    content: content
                });
                dialog.dialog('close');
            });

            dialog.find('.mfw-cancel').on('click', function() {
                dialog.dialog('close');
            });
        },

        showMessage: function(message, type = 'success') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `).insertBefore('.mfw-admin-content');

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showError: function(message) {
            this.showMessage(message, 'error');
        },

        updatePreview: function() {
            const prompt = $(this).val();
            if (prompt.length > 10) {
                MFWAdmin.generatePreview(prompt);
            }
        },

        generatePreview: _.debounce(function(prompt) {
            $.ajax({
                url: MFWAdmin.settings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mfw_generate_preview',
                    nonce: MFWAdmin.settings.nonce,
                    prompt: prompt
                },
                success: function(response) {
                    if (response.success) {
                        $('#mfw-preview').html(response.data);
                    }
                }
            });
        }, 500)
    };

    $(document).ready(function() {
        MFWAdmin.init();
    });

})(jQuery);