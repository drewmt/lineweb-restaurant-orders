/* globals jQuery */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// Variant (size) rows
	// -------------------------------------------------------------------------
	$(document).on('click', '#mfm-add-size', function () {
		var $list = $('#mfm-size-list');
		var index = $list.find('.mfm-size-row').length;
		var html  =
			'<div class="mfm-size-row flex gap-2 items-center mb-2">' +
				'<input type="text" name="mfm_size[' + index + '][label]" placeholder="Label (e.g. Large)" class="regular-text" />' +
				'<input type="number" name="mfm_size[' + index + '][price]" placeholder="Price" step="0.01" min="0" class="small-text" />' +
				'<button type="button" class="button mfm-remove-size">Remove</button>' +
			'</div>';
		$list.append(html);
	});

	$(document).on('click', '.mfm-remove-size', function () {
		$(this).closest('.mfm-size-row').remove();
		// Re-index remaining rows so indices stay contiguous.
		$('#mfm-size-list .mfm-size-row').each(function (i) {
			$(this).find('[name^="mfm_size["]').each(function () {
				var name = $(this).attr('name').replace(/mfm_size\[\d+\]/, 'mfm_size[' + i + ']');
				$(this).attr('name', name);
			});
		});
	});

	// -------------------------------------------------------------------------
	// Extra rows
	// -------------------------------------------------------------------------
	$(document).on('click', '#mfm-add-extra', function () {
		var $list = $('#mfm-extras-list');
		var index = $list.find('.mfm-extra-row').length;
		var html  =
			'<div class="mfm-extra-row flex gap-2 items-center mb-2">' +
				'<input type="text" name="mfm_extras[' + index + '][label]" placeholder="Extra (e.g. Extra cheese)" class="regular-text" />' +
				'<input type="number" name="mfm_extras[' + index + '][price]" placeholder="Price" step="0.01" min="0" class="small-text" />' +
				'<button type="button" class="button mfm-remove-extra">Remove</button>' +
			'</div>';
		$list.append(html);
	});

	$(document).on('click', '.mfm-remove-extra', function () {
		$(this).closest('.mfm-extra-row').remove();
		// Re-index remaining rows.
		$('#mfm-extras-list .mfm-extra-row').each(function (i) {
			$(this).find('[name^="mfm_extras["]').each(function () {
				var name = $(this).attr('name').replace(/mfm_extras\[\d+\]/, 'mfm_extras[' + i + ']');
				$(this).attr('name', name);
			});
		});
	});

	// -------------------------------------------------------------------------
	// Dietary checkboxes — toggle labels for UX
	// -------------------------------------------------------------------------
	$(document).on('change', '.mfm-dietary-cb', function () {
		var $label = $(this).closest('label');
		if ($(this).is(':checked')) {
			$label.addClass('text-green-700');
		} else {
			$label.removeClass('text-green-700');
		}
	});

}(jQuery));
