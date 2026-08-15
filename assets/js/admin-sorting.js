jQuery(document).ready(function ($) {
    var list = $('#the-list');

    if (list.length === 0) {
        return;
    }

    list.sortable({
        handle: '.snaporder-drag-handle',
        placeholder: 'snaporder-sortable-placeholder',
        forcePlaceholderSize: true,
        helper: function (e, ui) {
            ui.children().each(function () {
                $(this).width($(this).width());
            });
            return ui;
        },
        update: function (event, ui) {
            var order = [];

            list.children().each(function () {
                var id = $(this).attr('id');
                if (id) {
                    // Extract ID from string like "post-123" or "tag-123"
                    var numericId = id.replace(/[^0-9]/g, '');
                    if (numericId) {
                        order.push(numericId);
                    }
                }
            });

            // Show saving indicator
            $('.snaporder-drag-handle').css('cursor', 'wait');

            $.ajax({
                url: snaporder_sorting.ajax_url,
                type: 'POST',
                data: {
                    action: 'snaporder_update_order',
                    nonce: snaporder_sorting.nonce,
                    order: order,
                    type: snaporder_sorting.type
                },
                success: function (response) {
                    if (response.success) {
                        // Flash success
                        var row = ui.item;
                        row.css('background-color', '#e6fffa');
                        setTimeout(function () {
                            row.css('background-color', '');
                        }, 1000);
                    } else {
                        alert('Error updating order');
                    }
                    $('.snaporder-drag-handle').css('cursor', 'move');
                },
                error: function () {
                    alert('Error updating order');
                    $('.snaporder-drag-handle').css('cursor', 'move');
                }
            });
        }
    });
});
