(function( $ ) {
	'use strict';
	$(function() {
		$(document).on('click', '.ovc-select-audio', function(e) {
			e.preventDefault();

			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var input = $(this).closest('td').find('.ovc-audio-url').first();
			var frame = wp.media({
				title: 'Choose voice audio',
				button: { text: 'Use this audio' },
				library: { type: 'audio' },
				multiple: false
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first();

				if (attachment) {
					input.val(attachment.toJSON().url).trigger('change');
				}
			});
			frame.open();
		});
	});

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

})( jQuery );
