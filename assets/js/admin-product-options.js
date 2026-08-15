/* globals jQuery */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// Variant (size) rows
	// -------------------------------------------------------------------------
	$(document).on('click', '#snaporder-add-size', function () {
		var $list = $('#snaporder-size-list');
		var index = $list.find('.snaporder-size-row').length;
		var html  =
			'<div class="snaporder-size-row flex gap-2 items-center mb-2">' +
				'<input type="text" name="snaporder_size[' + index + '][label]" placeholder="Label (e.g. Large)" class="regular-text" />' +
				'<input type="number" name="snaporder_size[' + index + '][price]" placeholder="Price" step="0.01" min="0" class="small-text" />' +
				'<button type="button" class="button snaporder-remove-size">Remove</button>' +
			'</div>';
		$list.append(html);
	});

	$(document).on('click', '.snaporder-remove-size', function () {
		$(this).closest('.snaporder-size-row').remove();
		// Re-index remaining rows so indices stay contiguous.
		$('#snaporder-size-list .snaporder-size-row').each(function (i) {
			$(this).find('[name^="snaporder_size["]').each(function () {
				var name = $(this).attr('name').replace(/snaporder_size\[\d+\]/, 'snaporder_size[' + i + ']');
				$(this).attr('name', name);
			});
		});
	});

	// -------------------------------------------------------------------------
	// Extra rows
	// -------------------------------------------------------------------------
	$(document).on('click', '#snaporder-add-extra', function () {
		var $list = $('#snaporder-extras-list');
		var index = $list.find('.snaporder-extra-row').length;
		var html  =
			'<div class="snaporder-extra-row flex gap-2 items-center mb-2">' +
				'<input type="text" name="snaporder_extras[' + index + '][label]" placeholder="Extra (e.g. Extra cheese)" class="regular-text" />' +
				'<input type="number" name="snaporder_extras[' + index + '][price]" placeholder="Price" step="0.01" min="0" class="small-text" />' +
				'<button type="button" class="button snaporder-remove-extra">Remove</button>' +
			'</div>';
		$list.append(html);
	});

	$(document).on('click', '.snaporder-remove-extra', function () {
		$(this).closest('.snaporder-extra-row').remove();
		// Re-index remaining rows.
		$('#snaporder-extras-list .snaporder-extra-row').each(function (i) {
			$(this).find('[name^="snaporder_extras["]').each(function () {
				var name = $(this).attr('name').replace(/snaporder_extras\[\d+\]/, 'snaporder_extras[' + i + ']');
				$(this).attr('name', name);
			});
		});
	});

	// -------------------------------------------------------------------------
	// Dietary checkboxes — toggle labels for UX
	// -------------------------------------------------------------------------
	$(document).on('change', '.snaporder-dietary-cb', function () {
		var $label = $(this).closest('label');
		if ($(this).is(':checked')) {
			$label.addClass('text-green-700');
		} else {
			$label.removeClass('text-green-700');
		}
	});

}(jQuery));
