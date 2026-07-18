/**
 * @package Modern Food Menu
 * @author Lineweb.gr - Andrew Matia
 * @copyright 2025 Lineweb.gr - Andrew Matia
 */
jQuery(document).ready(function ($) {
    // Color Picker
    $('.mfm-color-field').wpColorPicker();

    // Media Uploader
    var mediaUploader;
    $('#mfm-upload-logo').click(function (e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Logo',
            button: {
                text: 'Choose Logo'
            },
            multiple: false
        });
        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#mfm_brand_logo').val(attachment.url);
            const image = $('<img>', {
                src: attachment.url,
                alt: '',
                css: { maxWidth: '150px', height: 'auto' }
            });
            $('#mfm-logo-preview').empty().append(image);
            $('#mfm-remove-logo').show();
        });
        mediaUploader.open();
    });

    $('#mfm-remove-logo').click(function (e) {
        e.preventDefault();
        $('#mfm_brand_logo').val('');
        $('#mfm-logo-preview').html('');
        $(this).hide();
    });

    // Settings Tabs
    function switchTab(tabId) {

        $('.mfm-tab-link').removeClass('active');
        $('.mfm-tab-link[href="#' + tabId + '"]').addClass('active');

        $('.mfm-settings-section').removeClass('active');
        $('#' + tabId).addClass('active');

        // Update URL hash without jumping
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        } else {
            window.location.hash = tabId;
        }
    }

    $(document).on('click', '.mfm-tab-link', function (e) {
        e.preventDefault();
        var tabId = $(this).attr('href').substring(1);
        switchTab(tabId);
    });

    // Check hash on load
    var hash = window.location.hash.substring(1);
    if (hash && $('#' + hash).length) {
        switchTab(hash);
    } else {
        // Default to first tab
        $('.mfm-tab-link:first').click();
    }
});
