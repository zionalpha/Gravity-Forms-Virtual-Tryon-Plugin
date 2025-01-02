(function($) {
    'use strict';

    const VirtualTryOn = {
        init: function() {
            this.bindEvents();
            this.initializeFields();
        },

        bindEvents: function() {
            $(document).on('change', '.gf-virtual-tryon-field-select', this.handleFieldChange);
            $(document).on('click', '.gf-virtual-tryon-test', this.handleTestProcess);
        },

        initializeFields: function() {
            $('.gf-virtual-tryon-field-select').each(function() {
                VirtualTryOn.updateFieldOptions($(this));
            });
        },

        handleFieldChange: function(e) {
            const $select = $(e.currentTarget);
            VirtualTryOn.updateFieldOptions($select);
        },

        handleTestProcess: async function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $container = $button.closest('.gf-virtual-tryon-settings');
            
            try {
                $container.addClass('gf-virtual-tryon-loading');
                const response = await $.post(ajaxurl, {
                    action: 'gf_virtual_tryon_test',
                    nonce: gfVirtualTryonVars.nonce,
                    form_id: gf_form_id
                });
                
                if (response.success) {
                    alert('Test successful!');
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                $container.removeClass('gf-virtual-tryon-loading');
            }
        },

        updateFieldOptions: function($select) {
            const selectedType = $select.val();
            const $relatedFields = $select.closest('.gf-virtual-tryon-field-map')
                                        .find('.gf-virtual-tryon-field-options');
            
            $relatedFields.toggle(selectedType === 'file');
        }
    };

    $(document).ready(function() {
        VirtualTryOn.init();
    });
})(jQuery);