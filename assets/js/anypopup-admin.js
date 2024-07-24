jQuery(document).ready(function ($) {
    var $popupForm = $('#popup-form');
    var $popupTable = $('.wp-list-table');
    var $displayPages = $('#popup-display-pages');

    // Initialize Select2
    $displayPages.select2({
        placeholder: "Select pages",
        allowClear: true
    });

    $('#add-new-popup').on('click', function () {
        resetForm();
        $popupForm.show();
    });

    $('#cancel-popup').on('click', function () {
        $popupForm.hide();
    });

    $('.edit-popup').on('click', function () {
        var popupId = $(this).data('id');
        $.ajax({
            url: anypopup_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'anypopup_get',
                id: popupId,
                nonce: anypopup_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    populateForm(response.data);
                    $popupForm.show();
                } else {
                    alert('Error loading popup data');
                }
            }
        });
    });

    $('#anypopup-form').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        formData += '&action=anypopup_save&nonce=' + anypopup_ajax.nonce;

        $.ajax({
            url: anypopup_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    $popupForm.hide();
                    location.reload(); // Refresh the page to update the table
                } else {
                    alert('Error saving popup data');
                }
            }
        });
    });

    // Handle "Select All" option
    $displayPages.on('select2:select', function (e) {
        if (e.params.data.id === 'select-all') {
            $displayPages.find('option').prop('selected', true);
            $displayPages.trigger('change');
        }
    });

    $displayPages.on('select2:unselect', function (e) {
        if (e.params.data.id === 'select-all') {
            $displayPages.find('option').prop('selected', false);
            $displayPages.trigger('change');
        }
    });

    function resetForm() {
        $('#popup-id').val('');
        $('#anypopup-form')[0].reset();
        $('#popup-display-pages').val(null).trigger('change');
    }

    function populateForm(data) {
        $('#popup-id').val(data.id);
        $('#popup-name').val(data.name);
        $('#popup-is-active').prop('checked', data.is_active);
        $('#popup-content').val(data.content);
        $('#popup-frequency').val(data.display_frequency);
        $('#popup-delay').val(data.delay_time);
        $('#popup-closed-delay').val(data.closed_display_delay);

        $('#popup-display-pages').val(data.display_pages).trigger('change');
    }
});