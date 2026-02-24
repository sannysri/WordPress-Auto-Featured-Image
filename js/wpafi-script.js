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

	function initSelect2(force = false) {
		$('.wpafi-select2').each(function () {
			const $this = $(this);
			if (force && $this.hasClass('select2-hidden-accessible')) {
				$this.select2('destroy');
			}

			if (!$this.hasClass('select2-hidden-accessible')) {
				const isBulk = $this.attr('id') === 'wpafi-bulk-rule';
				$this.select2({
					placeholder: isBulk ? 'Select a rule' : 'Select options',
					allowClear: !isBulk,
					width: isBulk ? '100%' : 'resolve',
					dropdownAutoWidth: true,
				});
			}
		});
	}

	initSelect2();

	// ============================================
	// Convert Settings Errors to Toast
	// ============================================

	(function () {
		// Check both our hidden container AND any auto-injected notices by WordPress.
		// Exclude review notice - it should stay as a banner.
		const $allNotices = $(
			'#wpafi-settings-messages .notice, .wpafi-settings-wrap .notice:not(.wpafi-review-notice), .wpafi-settings-wrap .updated, .wpafi-settings-wrap .error'
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
	// Bulk Operations with Batched Processing
	// ============================================

	const BATCH_SIZE = 50; // Process 50 posts per batch.
	let bulkState = {
		totalUpdated: 0,
		totalFailed: 0,
		cancelled: false,
	};

	$('#wpafi-bulk-assign').on('click', function (e) {
		e.preventDefault();

		// Show custom warning modal.
		$('#wpafi-warning-modal').fadeIn(200);
	});

	// Modal cancel button.
	$('#wpafi-modal-cancel, .wpafi-modal-overlay').on('click', function () {
		$('#wpafi-warning-modal').fadeOut(200);
		bulkState.cancelled = true;
		wpafi.toast('Bulk operation cancelled', 'warning');
	});

	// Modal confirm button.
	$('#wpafi-modal-confirm').on('click', function () {
		$('#wpafi-warning-modal').fadeOut(200);
		runBulkOperation();
	});

	// Actual bulk operation function with batching.
	function runBulkOperation() {
		const $button = $('#wpafi-bulk-assign');
		const $spinner = $('#wpafi-bulk-spinner');
		const $result = $('#wpafi-bulk-result');
		const $progressContainer = $('#wpafi-progress-container');
		const ruleIdx = $('#wpafi-bulk-rule').val();

		// Reset state.
		bulkState = { totalUpdated: 0, totalFailed: 0, cancelled: false };

		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		$progressContainer.show();
		wpafi.updateProgress(0, 'Counting posts...');

		// First, get total count.
		$.ajax({
			url: wpafi_vars.ajax_url,
			type: 'POST',
			data: {
				action: 'wpafi_bulk_count',
				nonce: wpafi_vars.bulk_nonce,
				rule_idx: ruleIdx,
			},
			success(response) {
				if (!response.success) {
					finishBulkOperation(
						$button,
						$spinner,
						$progressContainer,
						$result,
						false,
						response.data.message
					);
					return;
				}

				const total = response.data.total;
				if (total === 0) {
					finishBulkOperation(
						$button,
						$spinner,
						$progressContainer,
						$result,
						true,
						'No posts found to process.'
					);
					return;
				}

				wpafi.updateProgress(
					0,
					'Processing 0 of ' + total + ' posts...'
				);

				// Start processing batches.
				processBatch(
					ruleIdx,
					0,
					total,
					$button,
					$spinner,
					$progressContainer,
					$result
				);
			},
			error() {
				finishBulkOperation(
					$button,
					$spinner,
					$progressContainer,
					$result,
					false,
					'Failed to count posts. Please try again.'
				);
			},
		});
	}

	// Process a single batch.
	function processBatch(
		ruleIdx,
		offset,
		total,
		$button,
		$spinner,
		$progressContainer,
		$result
	) {
		if (bulkState.cancelled) {
			finishBulkOperation(
				$button,
				$spinner,
				$progressContainer,
				$result,
				true,
				'Operation cancelled. Updated ' +
					bulkState.totalUpdated +
					' posts.'
			);
			return;
		}

		$.ajax({
			url: wpafi_vars.ajax_url,
			type: 'POST',
			data: {
				action: 'wpafi_bulk_assign',
				nonce: wpafi_vars.bulk_nonce,
				rule_idx: ruleIdx,
				offset: offset,
				limit: BATCH_SIZE,
			},
			success(response) {
				if (!response.success) {
					finishBulkOperation(
						$button,
						$spinner,
						$progressContainer,
						$result,
						false,
						response.data.message
					);
					return;
				}

				const data = response.data;
				bulkState.totalUpdated += data.updated;
				bulkState.totalFailed += data.failed;

				const progress = Math.round((data.processed / total) * 100);
				wpafi.updateProgress(
					progress,
					'Processing ' + data.processed + ' of ' + total + ' posts...'
				);

				if (data.has_more && !bulkState.cancelled) {
					// Process next batch.
					setTimeout(function () {
						processBatch(
							ruleIdx,
							data.next_offset,
							total,
							$button,
							$spinner,
							$progressContainer,
							$result
						);
					}, 100); // Small delay to prevent server overload.
				} else {
					// All done!
					const message =
						'Bulk operation complete. ' +
						bulkState.totalUpdated +
						' posts updated, ' +
						bulkState.totalFailed +
						' failed.';
					finishBulkOperation(
						$button,
						$spinner,
						$progressContainer,
						$result,
						true,
						message
					);
				}
			},
			error() {
				finishBulkOperation(
					$button,
					$spinner,
					$progressContainer,
					$result,
					false,
					'Batch failed at offset ' +
						offset +
						'. Updated ' +
						bulkState.totalUpdated +
						' posts before error.'
				);
			},
		});
	}

	// Finish bulk operation and reset UI.
	function finishBulkOperation(
		$button,
		$spinner,
		$progressContainer,
		$result,
		success,
		message
	) {
		$spinner.removeClass('is-active');
		$button.prop('disabled', false);

		if (success) {
			wpafi.updateProgress(100, 'Complete!');
			$result.html(
				'<div class="notice notice-success inline"><p>' +
					message +
					'</p></div>'
			);
			wpafi.toast(message, 'success');

			setTimeout(function () {
				$progressContainer.hide();
				wpafi.updateProgress(0, '');
			}, 3000);
		} else {
			wpafi.updateProgress(0, 'Error');
			$result.html(
				'<div class="notice notice-error inline"><p>' +
					message +
					'</p></div>'
			);
			wpafi.toast(message, 'error');
			$progressContainer.hide();
		}
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
		initSelect2();
	}

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

		// Use current count as index to avoid gaps after deletions.
		ruleIndex = currentRuleCount;

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

		// Re-index all rules to ensure consistent numbering.
		reindexAllRules();

		updateAddRuleButton();
		updateRuleLimitText();

		// New rules should start expanded.
		$('#wpafi-rules-container .wpafi-rule-card').last().removeClass('is-collapsed');
		$('#wpafi-rules-container .wpafi-rule-card').last().find('.wpafi-rule-collapsed-state').val('0');

		wpafi.toast('New rule added', 'success');
	});

	// Remove Rule button handler (delegated).
	$(document).on('click', '.wpafi-remove-rule', function (e) {
		e.preventDefault();
		$(this).closest('.wpafi-rule-card').remove();
		updateAddRuleButton();
		reindexAllRules();
		updateRuleLimitText();
		wpafi.toast('Rule removed', 'warning');
	});

	// Re-index all rules after add/remove to ensure consistent numbering.
	function reindexAllRules() {
		$('#wpafi-rules-container .wpafi-rule-card').each(function (i) {
			const $card = $(this);

			// Update display number.
			$card.find('.wpafi-rule-index').text(i + 1);

			// Update data-index attribute.
			$card.attr('data-index', i);

			// Update all form field names with new index.
			$card.find('[name*="wpafi_rules"]').each(function () {
				const $field = $(this);
				const name = $field.attr('name');
				if (name) {
					const newName = name.replace(
						/wpafi_rules\]\[\d+\]/,
						'wpafi_rules][' + i + ']'
					);
					$field.attr('name', newName);
				}
			});
		});

		// Update ruleIndex for next add.
		ruleIndex = $('#wpafi-rules-container .wpafi-rule-card').length;
	}

	// Legacy function name for compatibility.
	function updateRuleNumbers() {
		reindexAllRules();
	}

	// Update rule limit text.
	function updateRuleLimitText() {
		const currentRuleCount = $(
			'#wpafi-rules-container .wpafi-rule-card'
		).length;
		const $container = $('.wpafi-add-rule-container');
		const $limitElement = $container.find('.wpafi-rule-limit');

		if (currentRuleCount >= maxRules) {
			// At limit - show locked state with upgrade link (only if Pro teasers enabled).
			if (!$limitElement.hasClass('wpafi-rule-limit-locked')) {
				$limitElement.addClass('wpafi-rule-limit-locked');
				var upgradeHtml =
					wpafi_vars.show_pro_teasers
						? '<span class="wpafi-rule-limit-upgrade">— <a href="' +
								wpafi_vars.upgrade_url +
								'" target="_blank">' +
								wpafi_vars.upgrade_text +
								'</a></span>'
						: '';
				$limitElement.html(
					'<span class="dashicons dashicons-lock"></span>' +
						'<span>' +
						currentRuleCount +
						' of ' +
						maxRules +
						' rules used</span>' +
						upgradeHtml
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

	// ============================================
	// Rule Card Collapsible & Toggle
	// ============================================

	// Toggle card collapse.
	$(document).on(
		'click',
		'.wpafi-rule-card-header',
		function (e) {
			// Don't collapse if clicking inputs/buttons inside header.
			if (
				$(e.target).closest(
					'.wpafi-rule-name, .wpafi-rule-header-actions, .wpafi-toggle'
				).length
			) {
				return;
			}

			const $card = $(this).closest('.wpafi-rule-card');
			const $stateInput = $card.find('.wpafi-rule-collapsed-state');
			const isCollapsed = $card.hasClass('is-collapsed');
			const isDisabled = $card.hasClass('is-disabled');

			// If disabled, don't allow expanding, but allow collapsing.
			if (isDisabled && isCollapsed) {
				wpafi.toast('Please enable the rule first to edit it', 'warning');
				return;
			}

			if (isCollapsed) {
				$card.removeClass('is-collapsed');
				$stateInput.val('0');
			} else {
				$card.addClass('is-collapsed');
				$stateInput.val('1');
			}
		}
	);

	// Toggle card enabled/disabled.
	$(document).on('change', '.wpafi-rule-enabled-toggle', function () {
		const $card = $(this).closest('.wpafi-rule-card');
		const $stateInput = $card.find('.wpafi-rule-collapsed-state');
		const isEnabled = $(this).is(':checked');

		if (isEnabled) {
			$card.removeClass('is-disabled');
			$card.removeClass('is-collapsed');
			$stateInput.val('0');
			wpafi.toast('Rule enabled', 'success');
		} else {
			$card.addClass('is-disabled');
			$card.addClass('is-collapsed');
			$stateInput.val('1');
			wpafi.toast('Rule disabled', 'warning');
		}

		// Update bulk rule dropdown sync.
		updateBulkRuleDropdown();
	});

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

		// Re-initialize Select2 if it's the bulk tab or settings tab (ensures correct width/display).
		if (tabId === 'bulk' || tabId === 'settings') {
			setTimeout(() => {
				initSelect2(true);
			}, 50);
		}
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

		// Notify Select2 of the change.
		$dropdown.trigger('change');
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

	// ==========================================================================
	// Promotional Offer Banner
	// ==========================================================================

	/**
	 * Initialize offer banner functionality
	 */
	function initOfferBanner() {
		const $banner = $('.wpafi-offer-banner');
		if (!$banner.length) {
			return;
		}

		// Check if offer was previously dismissed (session-based).
		const dismissedKey = 'wpafi_offer_dismissed';
		// TESTING: Disabled session check - uncomment for production
		// if (sessionStorage.getItem(dismissedKey)) {
		// 	$banner.remove();
		// 	return;
		// }

		// Handle dismiss button - TESTING: Disabled for testing
		/*
		$banner.on('click', '.wpafi-offer-dismiss', function (e) {
			e.preventDefault();
			$banner.addClass('dismissed');
			sessionStorage.setItem(dismissedKey, '1');

			// Remove from DOM after animation.
			setTimeout(function () {
				$banner.remove();
			}, 300);
		});
		*/

		// Initialize countdown timer if present.
		const $countdown = $banner.find('.wpafi-offer-countdown');
		if ($countdown.length) {
			const endTime = $countdown.data('countdown');
			if (endTime) {
				initCountdownTimer($countdown.find('.wpafi-countdown-timer'), endTime);
			}
		}
	}

	/**
	 * Initialize countdown timer
	 *
	 * @param {jQuery} $element Timer element.
	 * @param {string} endTime  ISO 8601 datetime string.
	 */
	function initCountdownTimer($element, endTime) {
		const end = new Date(endTime).getTime();

		function updateTimer() {
			const now = new Date().getTime();
			const distance = end - now;

			// If countdown finished.
			if (distance < 0) {
				$element.text('Expired');
				$element.closest('.wpafi-offer-banner').addClass('dismissed');
				setTimeout(function () {
					$element.closest('.wpafi-offer-banner').remove();
				}, 300);
				return;
			}

			// Calculate time units.
			const days = Math.floor(distance / (1000 * 60 * 60 * 24));
			const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			const seconds = Math.floor((distance % (1000 * 60)) / 1000);

			// Format display.
			let display = '';
			if (days > 0) {
				display = days + 'd ' + hours + 'h ' + minutes + 'm';
			} else if (hours > 0) {
				display = hours + 'h ' + minutes + 'm ' + seconds + 's';
			} else {
				display = minutes + 'm ' + seconds + 's';
			}

			$element.text(display);

			// Update every second.
			setTimeout(updateTimer, 1000);
		}

		updateTimer();
	}

	// Initialize offer banner.
	initOfferBanner();
});
