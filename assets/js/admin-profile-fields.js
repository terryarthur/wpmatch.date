/**
 * WPMatch Profile Fields Admin JavaScript
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main admin object
    var WPMatchProfileFields = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initModals();
            this.initFieldTypeHandling();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Delete field confirmation
            $(document).on('click', '.wpmatch-delete-field', function(e) {
                e.preventDefault();
                self.confirmDeleteField($(this));
            });

            // Duplicate field
            $(document).on('click', '.wpmatch-duplicate-field', function(e) {
                e.preventDefault();
                self.duplicateField($(this));
            });

            // Toggle field status
            $(document).on('click', '.wpmatch-toggle-status', function(e) {
                e.preventDefault();
                self.toggleFieldStatus($(this));
            });

            // Preview field
            $(document).on('click', '.wpmatch-preview-field', function(e) {
                e.preventDefault();
                self.previewField($(this));
            });

            // Field type change
            $(document).on('change', '#field_type', function() {
                self.handleFieldTypeChange($(this));
            });

            // Form submission
            $(document).on('submit', '#wpmatch-field-form', function(e) {
                if (!self.validateForm($(this))) {
                    e.preventDefault();
                }
            });

            // Auto-generate field name from label
            $(document).on('input', '#field_label', function() {
                var fieldName = $('#field_name');
                if (fieldName.length && !fieldName.prop('readonly')) {
                    var generatedName = self.generateFieldName($(this).val());
                    fieldName.val(generatedName);
                }
            });

            // Modal close handlers
            $(document).on('click', '.wpmatch-modal-close, .wpmatch-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Escape key to close modal
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    self.closeModal();
                }
            });
        },

        /**
         * Initialize sortable functionality for field ordering
         */
        initSortable: function() {
            var self = this;
            
            $('#wpmatch-fields-sortable').sortable({
                handle: '.sortable-handle',
                placeholder: 'wpmatch-sort-placeholder',
                axis: 'y',
                cursor: 'move',
                update: function(event, ui) {
                    self.updateFieldOrder();
                },
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                }
            });
        },

        /**
         * Initialize modal functionality
         */
        initModals: function() {
            // Set up modal containers if they don't exist
            if (!$('#wpmatch-field-preview-modal').length) {
                $('body').append(this.getModalHTML());
            }
        },

        /**
         * Initialize field type specific handling
         */
        initFieldTypeHandling: function() {
            // Trigger field type change on page load to show/hide relevant options
            var fieldTypeSelect = $('#field_type');
            if (fieldTypeSelect.length && fieldTypeSelect.val()) {
                this.handleFieldTypeChange(fieldTypeSelect);
            }
        },

        /**
         * Handle field type change to show/hide relevant options
         */
        handleFieldTypeChange: function($select) {
            var selectedType = $select.val();
            var typeConfig = wpMatchFieldsAdmin.field_types[selectedType];
            
            // Hide all field options first
            $('.field-option').hide();
            
            if (typeConfig && typeConfig.supports) {
                var supports = typeConfig.supports;
                
                // Show supported options
                if (supports.includes('placeholder')) {
                    $('.placeholder-text-option').show();
                }
                if (supports.includes('help_text')) {
                    $('.help-text-option').show();
                }
                if (supports.includes('default_value')) {
                    $('.default-value-option').show();
                }
                if (supports.includes('min_length') || supports.includes('max_length')) {
                    $('.length-options').show();
                }
                if (supports.includes('min_value') || supports.includes('max_value')) {
                    $('.value-options').show();
                }
                if (supports.includes('options')) {
                    $('.choices-options').show();
                }
            }

            // Show field width option for all types
            $('.field-width-option').show();
            
            // Trigger custom event for type-specific handling
            $(document).trigger('wpmatch:fieldTypeChanged', [selectedType, typeConfig]);
        },

        /**
         * Confirm and delete field
         */
        confirmDeleteField: function($button) {
            var fieldId = $button.data('field-id');
            var fieldName = $button.closest('tr').find('.column-name strong').text();
            
            if (confirm(wpMatchFieldsAdmin.strings.confirm_delete)) {
                this.deleteField(fieldId);
            }
        },

        /**
         * Delete field via AJAX
         */
        deleteField: function(fieldId) {
            var self = this;
            
            $.ajax({
                url: wpMatchFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmatch_field_action',
                    field_action: 'delete_field',
                    field_id: fieldId,
                    nonce: wpMatchFieldsAdmin.nonce
                },
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        // Remove row from table
                        $('tr[data-field-id="' + fieldId + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                        self.showNotice(response.message, 'success');
                    } else {
                        self.showNotice(response.message || wpMatchFieldsAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotice(wpMatchFieldsAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Duplicate field via AJAX
         */
        duplicateField: function($button) {
            var fieldId = $button.data('field-id');
            var self = this;
            
            $.ajax({
                url: wpMatchFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmatch_field_action',
                    field_action: 'duplicate_field',
                    field_id: fieldId,
                    nonce: wpMatchFieldsAdmin.nonce
                },
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        self.showNotice(response.message, 'success');
                        // Reload page to show duplicated field
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showNotice(response.message || wpMatchFieldsAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotice(wpMatchFieldsAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Toggle field status via AJAX
         */
        toggleFieldStatus: function($button) {
            var fieldId = $button.data('field-id');
            var currentStatus = $button.data('current-status');
            var self = this;
            
            $.ajax({
                url: wpMatchFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmatch_field_action',
                    field_action: 'toggle_status',
                    field_id: fieldId,
                    current_status: currentStatus,
                    nonce: wpMatchFieldsAdmin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text(wpMatchFieldsAdmin.strings.saving);
                },
                success: function(response) {
                    if (response.success) {
                        var newStatus = response.new_status;
                        
                        // Update button
                        $button.data('current-status', newStatus);
                        $button.text(newStatus === 'active' ? 'Deactivate' : 'Activate');
                        
                        // Update status badge
                        var $row = $button.closest('tr');
                        var $statusBadge = $row.find('.status-badge');
                        $statusBadge.removeClass('status-active status-inactive')
                                   .addClass('status-' + newStatus)
                                   .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                        
                        self.showNotice(response.message, 'success');
                    } else {
                        self.showNotice(response.message || wpMatchFieldsAdmin.strings.error, 'error');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    self.showNotice(wpMatchFieldsAdmin.strings.error, 'error');
                    $button.prop('disabled', false).text(currentStatus === 'active' ? 'Deactivate' : 'Activate');
                }
            });
        },

        /**
         * Preview field via AJAX
         */
        previewField: function($button) {
            var fieldId = $button.data('field-id');
            var self = this;
            
            $.ajax({
                url: wpMatchFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmatch_field_action',
                    field_action: 'get_field_preview',
                    field_id: fieldId,
                    nonce: wpMatchFieldsAdmin.nonce
                },
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        self.showFieldPreview(response.html, response.field);
                    } else {
                        self.showNotice(response.message || wpMatchFieldsAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showNotice(wpMatchFieldsAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Update field order via AJAX
         */
        updateFieldOrder: function() {
            var fieldOrder = {};
            var self = this;
            
            $('#wpmatch-fields-sortable tr').each(function(index) {
                var fieldId = $(this).data('field-id');
                if (fieldId) {
                    fieldOrder[fieldId] = (index + 1) * 10; // Increment by 10s for easier manual reordering
                    
                    // Update the order number display
                    $(this).find('.field-order-number').text((index + 1) * 10);
                }
            });
            
            $.ajax({
                url: wpMatchFieldsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmatch_field_action',
                    field_action: 'update_order',
                    field_order: fieldOrder,
                    nonce: wpMatchFieldsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.message, 'success');
                    } else {
                        self.showNotice(response.message || wpMatchFieldsAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showNotice(wpMatchFieldsAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Validate form before submission
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Clear previous error states
            $form.find('.error').removeClass('error');
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    errors.push($field.prev('label').text() + ' ' + wpMatchFieldsAdmin.strings.field_required);
                    isValid = false;
                }
            });
            
            // Validate field name format
            var fieldName = $('#field_name').val();
            if (fieldName && !fieldName.match(/^[a-z][a-z0-9_]*[a-z0-9]$/)) {
                $('#field_name').addClass('error');
                errors.push('Field name must start with a letter, contain only lowercase letters, numbers, and underscores, and end with a letter or number.');
                isValid = false;
            }
            
            // Show errors if any
            if (errors.length > 0) {
                this.showNotice(errors.join('<br>'), 'error');
            }
            
            return isValid;
        },

        /**
         * Generate field name from label
         */
        generateFieldName: function(label) {
            return label.toLowerCase()
                       .replace(/[^a-z0-9\s]/g, '') // Remove special characters
                       .replace(/\s+/g, '_')        // Replace spaces with underscores
                       .replace(/^_+|_+$/g, '')     // Remove leading/trailing underscores
                       .substring(0, 50);           // Limit length
        },

        /**
         * Show field preview in modal
         */
        showFieldPreview: function(html, field) {
            var $modal = $('#wpmatch-field-preview-modal');
            var $content = $modal.find('#wpmatch-field-preview-content');
            
            // Set content
            $content.html('<div class="field-preview-wrapper">' +
                         '<h4>' + field.field_label + ' (' + field.field_type + ')</h4>' +
                         '<div class="field-preview-field">' + html + '</div>' +
                         '</div>');
            
            // Show modal
            this.showModal($modal);
        },

        /**
         * Show modal
         */
        showModal: function($modal) {
            $modal.show();
            $('body').addClass('wpmatch-modal-open');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.wpmatch-modal').hide();
            $('body').removeClass('wpmatch-modal-open');
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            if (!$('#wpmatch-loading').length) {
                $('body').append('<div id="wpmatch-loading" class="wpmatch-loading"><div class="wpmatch-spinner"></div></div>');
            }
            $('#wpmatch-loading').show();
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('#wpmatch-loading').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible wpmatch-admin-notice">' +
                           '<p>' + message + '</p>' +
                           '<button type="button" class="notice-dismiss">' +
                           '<span class="screen-reader-text">Dismiss this notice.</span>' +
                           '</button>' +
                           '</div>');
            
            $('.wpmatch-profile-fields-wrap h1').after($notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
            
            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut();
            });
        },

        /**
         * Get modal HTML structure
         */
        getModalHTML: function() {
            return '<div id="wpmatch-field-preview-modal" class="wpmatch-modal" style="display: none;">' +
                   '<div class="wpmatch-modal-content">' +
                   '<div class="wpmatch-modal-header">' +
                   '<h3>Field Preview</h3>' +
                   '<button type="button" class="wpmatch-modal-close">&times;</button>' +
                   '</div>' +
                   '<div class="wpmatch-modal-body">' +
                   '<div id="wpmatch-field-preview-content"></div>' +
                   '</div>' +
                   '</div>' +
                   '</div>';
        }
    };

    /**
     * Create default dating fields
     */
    window.wpmatchCreateDefaultFields = function() {
        if (!confirm('This will create default dating profile fields. Continue?')) {
            return;
        }

        var data = {
            action: 'wpmatch_create_default_fields',
            nonce: wpMatchFieldsAdmin.nonce
        };

        $.post(ajaxurl, data)
            .done(function(response) {
                if (response.success) {
                    WPMatchProfileFields.showNotice('success', response.data.message);
                    // Refresh the page to show new fields
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    WPMatchProfileFields.showNotice('error', response.data || 'Failed to create default fields.');
                }
            })
            .fail(function() {
                WPMatchProfileFields.showNotice('error', 'AJAX request failed.');
            });
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPMatchProfileFields.init();
    });

    // Expose to global scope for extensibility
    window.WPMatchProfileFields = WPMatchProfileFields;

})(jQuery);