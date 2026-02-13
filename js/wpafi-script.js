/**
 * Admin JavaScript for WP Auto Featured Image
 * Modern UI with toast notifications and progress tracking
 *
 * @package
 * @since 2.1.0
 */

jQuery(document).ready(function ($) {
	// ============================================
	// Toast Notification System
	// ============================================

	const wpafi = {
		toast(message, type) {
			type = type || 'success';
			const icons = {
				success: 'dashicons-yes-alt',
				error: 'dashicons-warning',
				warning: 'dashicons-info',
			};

			const $toast = $(
				'<div class="wpafi-toast ' +
					type +
					'">' +
					'<span class="dashicons ' +
					icons[type] +
					' wpafi-toast-icon"></span>' +
					'<span class="wpafi-toast-message">' +
					message +
					'</span>' +
					'<button type="button" class="wpafi-toast-close"><span class="dashicons dashicons-no-alt"></span></button>' +
					'</div>'
			);

			$('#wpafi-toast-container').append($toast);

			// Auto-dismiss after 5 seconds.
			setTimeout(function () {
				wpafi.dismissToast($toast);
			}, 5000);

			// Manual dismiss.
			$toast.find('.wpafi-toast-close').on('click', function () {
				wpafi.dismissToast($toast);
			});
		},

		dismissToast($toast) {
			$toast.addClass('hiding');
			setTimeout(function () {
				$toast.remove();
			}, 300);
		},

		updateProgress(percent, text) {
			$('#wpafi-progress-fill').css('width', percent + '%');
			$('#wpafi-progress-text').text(text || percent + '%');
		},
	};

	// ============================================
	// Default Image Upload
	// ============================================

	$('#upload_default_thumb').on('click', function (e) {
		e.preventDefault();

		const mediaUploader = wp.media({
			title: wpafi_vars.upload_button_text || 'Select Default Image',
			multiple: false,
			library: { type: 'image' },
		});

		mediaUploader.on('select', function () {
			const attachment = mediaUploader
				.state()
				.get('selection')
				.first()
				.toJSON();

			// Update preview with proper styling.
			$('#uploaded_thumb_preview').html(
				'<img src="' +
					attachment.url +
					'" alt="Default featured image">'
			);

			// Update hidden input.
			$('#default_thumb_id').val(attachment.id);

			// Show remove button.
			$('#delete_thumb').show();

			wpafi.toast('Image selected successfully!', 'success');
		});

		mediaUploader.open();
	});

	// Delete thumbnail handler.
	$('#delete_thumb').on('click', function () {
		$('#uploaded_thumb_preview').html(
			'<div class="wpafi-no-image">' +
				'<span class="dashicons dashicons-plus-alt2"></span>' +
				'<span>No image selected</span>' +
				'</div>'
		);
		$('#default_thumb_id').val('');
		$(this).hide();
		wpafi.toast('Image removed', 'warning');
	});

	// ============================================
	// Select2 Initialization
	// ============================================

	$('.wpafi-select').select2({
		placeholder: 'Select options',
		allowClear: true,
	});

	// Initialize Select2 on bulk dropdown.
	$('.wpafi-bulk-select2').select2({
		placeholder: 'Select a rule',
		allowClear: false,
		width: '300px',
	});

	// ============================================
	// Convert Settings Errors to Toast
	// ============================================

	(function () {
		// Check both our hidden container AND any auto-injected notices by WordPress.
		const $allNotices = $(
			'#wpafi-settings-messages .notice, .wpafi-settings-wrap .notice, .wpafi-settings-wrap .updated, .wpafi-settings-wrap .error'
		);

		$allNotices.each(function () {
			const $notice = $(this);
			const message = $notice.find('p').text() || $notice.text();
			let type = 'success';

			if ($notice.hasClass('notice-error') || $notice.hasClass('error')) {
				type = 'error';
			} else if ($notice.hasClass('notice-warning')) {
				type = 'warning';
			}

			if (message.trim()) {
				wpafi.toast(message.trim(), type);
			}

			// Remove the original notice after converting to toast.
			$notice.remove();
		});
	})();

	// ============================================
	// Bulk Operations with Progress
	// ============================================

	$('#wpafi-bulk-assign').on('click', function (e) {
		e.preventDefault();

		// Show custom warning modal.
		$('#wpafi-warning-modal').fadeIn(200);
	});

	// Modal cancel button.
	$('#wpafi-modal-cancel, .wpafi-modal-overlay').on('click', function () {
		$('#wpafi-warning-modal').fadeOut(200);
		wpafi.toast('Bulk operation cancelled', 'warning');
	});

	// Modal confirm button.
	$('#wpafi-modal-confirm').on('click', function () {
		$('#wpafi-warning-modal').fadeOut(200);
		runBulkOperation();
	});

	// Actual bulk operation function.
	function runBulkOperation() {
		const $button = $('#wpafi-bulk-assign');
		const $spinner = $('#wpafi-bulk-spinner');
		const $result = $('#wpafi-bulk-result');
		const $progressContainer = $('#wpafi-progress-container');

		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		$progressContainer.show();
		wpafi.updateProgress(0, 'Starting...');

		// Simulated progress (actual batch processing can be added later).
		const progressInterval = setInterval(function () {
			const current =
				(parseInt($('#wpafi-progress-fill').css('width')) /
					$('#wpafi-progress-bar').width()) *
				100;
			if (current < 90) {
				wpafi.updateProgress(
					Math.min(current + 10, 90),
					'Processing...'
				);
			}
		}, 500);

		$.ajax({
			url: wpafi_vars.ajax_url,
			type: 'POST',
			data: {
				action: 'wpafi_bulk_assign',
				nonce: wpafi_vars.bulk_nonce,
			},
			success(response) {
				clearInterval(progressInterval);
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);

				if (response.success) {
					wpafi.updateProgress(100, 'Complete!');
					$result.html(
						'<div class="notice notice-success inline"><p>' +
							response.data.message +
							'</p></div>'
					);
					wpafi.toast(response.data.message, 'success');

					setTimeout(function () {
						$progressContainer.hide();
						wpafi.updateProgress(0, '');
					}, 3000);
				} else {
					wpafi.updateProgress(0, 'Error');
					$result.html(
						'<div class="notice notice-error inline"><p>' +
							response.data.message +
							'</p></div>'
					);
					wpafi.toast(response.data.message, 'error');
					$progressContainer.hide();
				}
			},
			error() {
				clearInterval(progressInterval);
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
				$progressContainer.hide();
				$result.html(
					'<div class="notice notice-error inline"><p>An error occurred. Please try again.</p></div>'
				);
				wpafi.toast('An error occurred. Please try again.', 'error');
			},
		});
	}

	// ============================================
	// Help Tooltips
	// ============================================

	$('.wpafi-help-tip').each(function () {
		const $tip = $(this);
		const title = $tip.attr('title');
		$tip.removeAttr('title');

		$tip.on('mouseenter', function () {
			const $tooltip = $(
				'<div class="wpafi-tooltip">' + title + '</div>'
			);
			$('body').append($tooltip);

			const tipOffset = $tip.offset();
			$tooltip.css({
				top: tipOffset.top - $tooltip.outerHeight() - 8,
				left:
					tipOffset.left -
					$tooltip.outerWidth() / 2 +
					$tip.outerWidth() / 2,
			});
		});

		$tip.on('mouseleave', function () {
			$('.wpafi-tooltip').remove();
		});
	});

	// ============================================
	// Conditional Rules Builder
	// ============================================

	const maxRules = wpafi_vars.max_rules || 2;
	let ruleIndex = $('#wpafi-rules-container .wpafi-rule-card').length;

	// Initialize Select2 on existing rules.
	function initRuleSelect2() {
		$('.wpafi-select2').each(function () {
			if (!$(this).hasClass('select2-hidden-accessible')) {
				$(this).select2({
					placeholder: 'Select...',
					allowClear: true,
					width: '100%',
				});
			}
		});
	}
	initRuleSelect2();

	// Add Rule button handler.
	$('#wpafi-add-rule').on('click', function (e) {
		e.preventDefault();

		const currentRuleCount = $(
			'#wpafi-rules-container .wpafi-rule-card'
		).length;

		if (currentRuleCount >= maxRules) {
			wpafi.toast(
				wpafi_vars.max_rules_message ||
					'Upgrade to Pro for unlimited rules!',
				'warning'
			);
			return;
		}

		let template = $('#wpafi-rule-template').html();
		template = template.replace(/\{\{INDEX\}\}/g, ruleIndex);

		$('#wpafi-rules-container').append(template);

		// Update rule number display.
		$('#wpafi-rules-container .wpafi-rule-card')
			.last()
			.find('.wpafi-rule-index')
			.text(ruleIndex + 1);

		// Initialize Select2 on new rule.
		initRuleSelect2();

		ruleIndex++;

		updateAddRuleButton();
		updateRuleLimitText();
		wpafi.toast('New rule added', 'success');
	});

	// Remove Rule button handler (delegated).
	$(document).on('click', '.wpafi-remove-rule', function (e) {
		e.preventDefault();
		$(this).closest('.wpafi-rule-card').remove();
		updateAddRuleButton();
		updateRuleNumbers();
		updateRuleLimitText();
		wpafi.toast('Rule removed', 'warning');
	});

	// Update rule numbers after removal.
	function updateRuleNumbers() {
		$('#wpafi-rules-container .wpafi-rule-card').each(function (i) {
			$(this)
				.find('.wpafi-rule-index')
				.text(i + 1);
		});
	}

	// Update rule limit text.
	function updateRuleLimitText() {
		const currentRuleCount = $(
			'#wpafi-rules-container .wpafi-rule-card'
		).length;
		const $container = $('.wpafi-add-rule-container');
		const $limitElement = $container.find('.wpafi-rule-limit');

		if (currentRuleCount >= maxRules) {
			// At limit - show locked state with upgrade link.
			if (!$limitElement.hasClass('wpafi-rule-limit-locked')) {
				$limitElement.addClass('wpafi-rule-limit-locked');
				$limitElement.html(
					'<span class="dashicons dashicons-lock"></span>' +
						'<span>' +
						currentRuleCount +
						' of ' +
						maxRules +
						' rules used</span>' +
						'<span class="wpafi-rule-limit-upgrade">' +
						'— <a href="https://sanny.dev/plugins/auto-featured-image-pro/?utm_source=plugin&utm_medium=add-btn&utm_campaign=upsell" target="_blank">Upgrade to add more</a>' +
						'</span>'
				);
			}
			$container.addClass('wpafi-add-rule-locked');
		} else {
			// Under limit - show normal state.
			$limitElement.removeClass('wpafi-rule-limit-locked');
			$limitElement.html(
				currentRuleCount + ' of ' + maxRules + ' rules used'
			);
			$container.removeClass('wpafi-add-rule-locked');
		}
	}

	// Update Add Rule button state.
	function updateAddRuleButton() {
		const currentRuleCount = $(
			'#wpafi-rules-container .wpafi-rule-card'
		).length;
		const $addBtn = $('#wpafi-add-rule');
		const $container = $('.wpafi-add-rule-container');

		if (currentRuleCount >= maxRules) {
			$addBtn.prop('disabled', true);
			$container.addClass('wpafi-add-rule-locked');
		} else {
			$addBtn.prop('disabled', false);
			$container.removeClass('wpafi-add-rule-locked');
		}
	}

	// Image select button and preview click handler (delegated).
	$(document).on(
		'click',
		'.wpafi-select-image, .wpafi-rule-image-preview',
		function (e) {
			e.preventDefault();

			const $element = $(this);
			const $card = $element.closest('.wpafi-rule-card');
			const $imageId = $card.find('.wpafi-rule-image-id');
			const $preview = $card.find('.wpafi-rule-image-preview');
			const $removeBtn = $card.find('.wpafi-remove-image');

			const mediaUploader = wp.media({
				title: wpafi_vars.select_image_title || 'Select Featured Image',
				button: { text: 'Use This Image' },
				multiple: false,
				library: { type: 'image' },
			});

			mediaUploader.on('select', function () {
				const attachment = mediaUploader
					.state()
					.get('selection')
					.first()
					.toJSON();
				$imageId.val(attachment.id);
				$preview.html(
					'<img src="' + attachment.url + '" alt="Featured image">'
				);
				$removeBtn.show();
				$card.removeClass('wpafi-rule-error'); // Clear validation error.
				wpafi.toast('Image selected', 'success');
			});

			mediaUploader.open();
		}
	);

	// Remove image from rule.
	$(document).on('click', '.wpafi-remove-image', function (e) {
		e.preventDefault();
		e.stopPropagation();

		const $card = $(this).closest('.wpafi-rule-card');
		const $imageId = $card.find('.wpafi-rule-image-id');
		const $preview = $card.find('.wpafi-rule-image-preview');

		$imageId.val('');
		$preview.html(
			'<div class="wpafi-rule-no-image">' +
				'<span class="dashicons dashicons-plus-alt2"></span>' +
				'<span>Select Image</span>' +
				'</div>'
		);
		$(this).hide();
		wpafi.toast('Image removed', 'warning');
	});

	// ============================================
	// Image Source Toggle
	// ============================================

	$(document).on(
		'change',
		'.wpafi-image-source-options input[type="radio"]',
		function () {
			const $card = $(this).closest('.wpafi-rule-card');
			const source = $(this).val();

			// Update active class on radio options.
			$card.find('.wpafi-radio-option').removeClass('active');
			$(this).closest('.wpafi-radio-option').addClass('active');

			// Hide all source panels.
			$card.find('.wpafi-source-panel').hide();

			// Show the selected source panel.
			if (source === 'media') {
				$card.find('.wpafi-source-media').fadeIn(200);
			} else if (source === 'first_image') {
				$card.find('.wpafi-source-first-image').fadeIn(200);
			} else if (source === 'external') {
				$card.find('.wpafi-source-external').fadeIn(200);
			}
		}
	);

	// Initialize rule button state.
	updateAddRuleButton();

	// ============================================
	// Tab Switching
	// ============================================

	$('.wpafi-tab-btn').on('click', function () {
		const tabId = $(this).data('tab');

		// Update tab buttons.
		$('.wpafi-tab-btn').removeClass('active');
		$(this).addClass('active');

		// Update tab panels.
		$('.wpafi-tab-panel').removeClass('active');
		$('#wpafi-tab-' + tabId).addClass('active');
	});

	// Update bulk rule dropdown when rules change.
	function updateBulkRuleDropdown() {
		const $dropdown = $('#wpafi-bulk-rule');
		const currentVal = $dropdown.val();

		// Keep the "All Rules" option.
		$dropdown.find('option:not([value="all"])').remove();

		// Add options for each rule.
		$('#wpafi-rules-container .wpafi-rule-card').each(function (i) {
			const $card = $(this);
			const ruleName = $card.find('.wpafi-rule-name').val();
			const displayName = ruleName || 'Rule #' + (i + 1);
			$dropdown.append(
				'<option value="' + i + '">' + displayName + '</option>'
			);
		});

		// Restore selection if still valid.
		if ($dropdown.find('option[value="' + currentVal + '"]').length) {
			$dropdown.val(currentVal);
		}
	}

	// Update dropdown on rule name change.
	$(document).on('input', '.wpafi-rule-name', function () {
		updateBulkRuleDropdown();
	});

	// Also update when adding/removing rules.
	$('#wpafi-add-rule').on('click.bulkUpdate', updateBulkRuleDropdown);
	$(document).on('click.bulkUpdate', '.wpafi-remove-rule', function () {
		setTimeout(updateBulkRuleDropdown, 100);
	});

	// ============================================
	// Form Validation
	// ============================================

	$('#wpafi-settings-form').on('submit', function (e) {
		let hasErrors = false;
		const errorMessages = [];

		// Validate each rule card.
		$('#wpafi-rules-container .wpafi-rule-card').each(function (index) {
			const $card = $(this);
			const imageId = $card.find('.wpafi-rule-image-id').val();
			const ruleName =
				$card.find('.wpafi-rule-name').val() || 'Rule #' + (index + 1);

			// Check if image is selected.
			if (!imageId || imageId === '0' || imageId === '') {
				hasErrors = true;
				errorMessages.push(ruleName + ': Please select an image');
				$card.addClass('wpafi-rule-error');
			} else {
				$card.removeClass('wpafi-rule-error');
			}
		});

		if (hasErrors) {
			e.preventDefault();

			// Show error toast for each message.
			errorMessages.forEach(function (msg) {
				wpafi.toast(msg, 'error');
			});

			// Scroll to first error.
			const $firstError = $('.wpafi-rule-error').first();
			if ($firstError.length) {
				$('html, body').animate(
					{
						scrollTop: $firstError.offset().top - 100,
					},
					300
				);
			}

			return false;
		}

		return true;
	});
});
