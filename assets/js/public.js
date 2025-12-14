/**
 * LazyBookings Public JS
 * - Auto-open native datepicker on click
 * - Service mode: load time slots via REST and allow selecting a slot
 * - Optional resource selection (when multiple resources available)
 * - Hotel mode: price preview
 */
(function($) {
    'use strict';

    function isoDateLocal(d) {
        var x = (d instanceof Date) ? d : new Date(d);
        var y = x.getFullYear();
        var m = String(x.getMonth() + 1).padStart(2, '0');
        var day = String(x.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function parseIsoDateLocal(s) {
        if (!s || typeof s !== 'string') return null;
        var parts = s.split('-');
        if (parts.length !== 3) return null;
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        var d = parseInt(parts[2], 10);
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }

    function startOfTodayLocal() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }

    function getRestRoot() {
        if (window.LTLB_PUBLIC && window.LTLB_PUBLIC.restRoot) {
            return String(window.LTLB_PUBLIC.restRoot).replace(/\/$/, '');
        }
        return null;
    }

    function openNativeDatePicker(inputEl) {
        if (!inputEl) return;
        try {
            if (typeof inputEl.showPicker === 'function') {
                inputEl.showPicker();
                return;
            }
        } catch (e) {
            // Ignore and fall back to focus
        }
        try {
            inputEl.focus();
        } catch (e2) {
            // ignore
        }
    }

    function initWidget($root) {
        var restRoot = getRestRoot();

        // Enable JS-only UX (stepper animations)
        $root.addClass('ltlb-js');

        var $stepper = $root.find('[data-ltlb-stepper]');
        var $panels = $stepper.find('[data-ltlb-step]');
        var activeStep = null;
        var lastStep = null;

        var $stepIndicator = $root.find('[data-ltlb-step-indicator]');
        var $stepCount = $stepIndicator.find('[data-ltlb-step-count]');
        var $stepTitle = $stepIndicator.find('[data-ltlb-step-title]');

        function getPanelTitle($panel) {
            if (!$panel || !$panel.length) return '';
            var $legend = $panel.find('legend').first();
            if (!$legend.length) return '';
            // Remove required marker etc.
            var $clone = $legend.clone();
            $clone.find('.ltlb-required').remove();
            return String($clone.text() || '').replace(/\s+/g, ' ').trim();
        }

        function syncStepIndicator() {
            if (!$stepIndicator.length) return;
            if (!activeStep) return;

            var $active = $panels.filter('[data-ltlb-step="' + activeStep + '"]');
            if (!$active.length) return;

            var $visible = getVisiblePanels();
            var idx = $visible.index($active);
            if (idx < 0) idx = 0;

            var current = idx + 1;
            var total = $visible.length || 1;
            var title = getPanelTitle($active);

            var stepText = window.ltlbI18n && window.ltlbI18n.step_of ? window.ltlbI18n.step_of : 'Step %s of %s';
            stepText = stepText.replace('%s', current).replace('%s', total);
            $stepCount.text(stepText);
            $stepTitle.text(title ? '— ' + title : '');
        }

        function getVisiblePanels() {
            return $panels.filter(function() {
                return $(this).css('display') !== 'none';
            });
        }

        function setStepperHeight() {
            if (!$stepper.length) return;
            var $active = $panels.filter('.is-active');
            if (!$active.length) return;
            var h = $active.outerHeight(true);
            if (!h || h <= 0) return;

            // Keep layout stable without animating container height (better on mobile).
            $stepper.css('height', h + 'px');
        }

        function setActiveStep(stepName) {
            var $target = $panels.filter('[data-ltlb-step="' + stepName + '"]');
            if (!$target.length) return;

            lastStep = activeStep;
            activeStep = stepName;

            $panels.removeClass('is-active is-left');

            if (lastStep) {
                var lastIndex = getVisiblePanels().index($panels.filter('[data-ltlb-step="' + lastStep + '"]'));
                var nextIndex = getVisiblePanels().index($target);
                if (nextIndex < lastIndex) {
                    // moving back: slide from left
                    $target.addClass('is-left');
                }
            }

            $target.addClass('is-active');
            syncStepIndicator();
            setTimeout(setStepperHeight, 0);
        }

        function getNextStepName(fromStep) {
            var $visible = getVisiblePanels();
            var $from = $panels.filter('[data-ltlb-step="' + fromStep + '"]');
            var idx = $visible.index($from);
            if (idx < 0) return null;
            var $next = $visible.eq(idx + 1);
            return $next.length ? String($next.data('ltlb-step')) : null;
        }

        function getPrevStepName(fromStep) {
            var $visible = getVisiblePanels();
            var $from = $panels.filter('[data-ltlb-step="' + fromStep + '"]');
            var idx = $visible.index($from);
            if (idx <= 0) return null;
            var $prev = $visible.eq(idx - 1);
            return $prev.length ? String($prev.data('ltlb-step')) : null;
        }

        function isValidForStep(stepName) {
            if (stepName === 'service') {
                return !!parseInt($root.find('select[name="service_id"]').val() || 0, 10);
            }

            // Service-mode datetime
            if (stepName === 'datetime' && $root.find('#ltlb-date').length) {
                var d = $root.find('#ltlb-date').val();
                var t = $root.find('#ltlb-time-slot').val();
                if (d) {
                    var dd = parseIsoDateLocal(d);
                    if (dd && dd < startOfTodayLocal()) return false;
                }
                return !!(d && t);
            }

            // Hotel-mode dates
            if (stepName === 'datetime' && $root.find('#ltlb-checkin').length) {
                var ci = $root.find('#ltlb-checkin').val();
                var co = $root.find('#ltlb-checkout').val();
                var guests = parseInt($root.find('#ltlb-guests').val() || 0, 10);
                if (!ci || !co || !guests) return false;
                // Basic sanity: checkout after checkin
                var ciD = parseIsoDateLocal(ci);
                var coD = parseIsoDateLocal(co);
                if (!ciD || !coD) return false;
                if (ciD < startOfTodayLocal()) return false;
                return coD > ciD;
            }

            // Resource step is optional
            if (stepName === 'resource') {
                return true;
            }

            if (stepName === 'details') {
                var email = $root.find('#ltlb-email').val();
                return !!email;
            }

            return true;
        }

        function syncNextButtons() {
            $panels.each(function() {
                var $panel = $(this);
                if ($panel.css('display') === 'none') return;
                var stepName = String($panel.data('ltlb-step'));
                var $next = $panel.find('[data-ltlb-next]');
                var $back = $panel.find('[data-ltlb-back]');

                if ($next.length) {
                    $next.prop('disabled', !isValidForStep(stepName));
                }
                if ($back.length) {
                    $back.prop('disabled', !getPrevStepName(stepName));
                }
            });
        }

        // Datepicker: open immediately on click.
        $root.find('input[type="date"]').on('click', function() {
            openNativeDatePicker(this);
        });

        var $serviceSelect = $root.find('select[name="service_id"]');

        // --- Hotel mode: price preview ---
        var $checkinInput = $root.find('#ltlb-checkin');
        var $checkoutInput = $root.find('#ltlb-checkout');
        var $pricePreview = $root.find('#ltlb-price-preview');
        var $priceAmount = $root.find('#ltlb-price-amount');
        var $priceBreakdown = $root.find('#ltlb-price-breakdown');

        function updatePricePreview() {
            if (!$pricePreview.length) return; // not hotel mode

            var selectedOption = $serviceSelect.find('option:selected');
            var priceCents = parseInt(selectedOption.data('price') || 0, 10);
            var checkin = $checkinInput.val();
            var checkout = $checkoutInput.val();

            if (priceCents > 0 && checkin && checkout) {
                var checkinDate = new Date(checkin);
                var checkoutDate = new Date(checkout);
                var nights = Math.max(0, Math.floor((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24)));

                if (nights > 0) {
                    var totalCents = priceCents * nights;
                    var pricePerNight = (priceCents / 100).toFixed(2);
                    var total = (totalCents / 100).toFixed(2);
                    var nightLabel = (nights === 1) ? 
                        (window.ltlbI18n && window.ltlbI18n.night ? window.ltlbI18n.night : 'night') : 
                        (window.ltlbI18n && window.ltlbI18n.nights ? window.ltlbI18n.nights : 'nights');

                    $priceAmount.text('€' + total);
                    $priceBreakdown.text(nights + ' ' + nightLabel + ' × €' + pricePerNight);
                    $pricePreview.show();
                } else {
                    $pricePreview.hide();
                }
            } else {
                $pricePreview.hide();
            }
        }

        $serviceSelect.on('change', updatePricePreview);
        $checkinInput.on('change', updatePricePreview);
        $checkoutInput.on('change', updatePricePreview);

        // Prevent selecting dates in the past (client-side)
        var todayIso = isoDateLocal(new Date());
        if ($root.find('#ltlb-date').length) {
            $root.find('#ltlb-date').attr('min', todayIso);
        }
        if ($checkinInput.length) {
            $checkinInput.attr('min', todayIso);
        }
        if ($checkoutInput.length) {
            $checkoutInput.attr('min', todayIso);
        }

        function syncHotelDateMins() {
            if (!$checkinInput.length || !$checkoutInput.length) return;
            var ci = $checkinInput.val();
            var minCo = todayIso;
            var ciD = parseIsoDateLocal(ci);
            if (ciD) {
                var next = new Date(ciD.getFullYear(), ciD.getMonth(), ciD.getDate() + 1);
                minCo = isoDateLocal(next);
            }
            $checkoutInput.attr('min', minCo);

            var co = $checkoutInput.val();
            if (co) {
                var coD = parseIsoDateLocal(co);
                var minD = parseIsoDateLocal(minCo);
                if (coD && minD && coD < minD) {
                    $checkoutInput.val(minCo);
                }
            }
        }

        $checkinInput.on('change', syncHotelDateMins);
        syncHotelDateMins();

        // --- Service mode: time slots + resources ---
        var $dateInput = $root.find('#ltlb-date');
        var $timeSelect = $root.find('#ltlb-time-slot');
        var $resourceStep = $root.find('#ltlb-resource-step');
        var $resourceSelect = $root.find('#ltlb-resource-select');
        var $resourceHint = $root.find('#ltlb-resource-hint');

        var startMode = String($root.data('ltlb-start-mode') || 'wizard');
        var prefillServiceId = parseInt($root.data('ltlb-prefill-service') || 0, 10);

        // Optional prefill (from query params via PHP data attributes)
        var prefillDate = String($root.data('ltlb-prefill-date') || '');
        var prefillTime = String($root.data('ltlb-prefill-time') || '');
        var prefillCheckin = String($root.data('ltlb-prefill-checkin') || '');
        var prefillCheckout = String($root.data('ltlb-prefill-checkout') || '');
        var prefillGuests = parseInt($root.data('ltlb-prefill-guests') || 0, 10);

        if ($dateInput.length && prefillDate && !$dateInput.val()) {
            $dateInput.val(prefillDate);
        }
        if ($checkinInput.length && prefillCheckin && !$checkinInput.val()) {
            $checkinInput.val(prefillCheckin);
        }
        if ($checkoutInput.length && prefillCheckout && !$checkoutInput.val()) {
            $checkoutInput.val(prefillCheckout);
        }
        if (prefillGuests && $root.find('#ltlb-guests').length) {
            $root.find('#ltlb-guests').val(String(prefillGuests));
        }

        function getInlineMessageEl($anchor, kind) {
            // kind: 'info' | 'error'
            if (!$anchor || !$anchor.length) return null;
            var $group = $anchor.closest('.ltlb-form-group');
            if (!$group.length) return null;

            var attr = kind === 'error' ? 'data-ltlb-ajax-error' : 'data-ltlb-ajax-info';
            var cls = kind === 'error' ? 'ltlb-error' : 'ltlb-info';
            var role = kind === 'error' ? 'alert' : 'status';
            var $existing = $group.find('[' + attr + ']').first();
            if ($existing.length) return $existing;

            var $el = $('<div/>', {
                'class': cls,
                'role': role,
                'aria-live': kind === 'error' ? 'assertive' : 'polite'
            }).attr(attr, '1').hide();

            $group.append($el);
            return $el;
        }

        function clearInlineMessages($anchor) {
            var $info = getInlineMessageEl($anchor, 'info');
            var $err = getInlineMessageEl($anchor, 'error');
            if ($info && $info.length) $info.hide().empty();
            if ($err && $err.length) $err.hide().text('');
        }

        function showInlineInfo($anchor, text, withSpinner) {
            var $info = getInlineMessageEl($anchor, 'info');
            var $err = getInlineMessageEl($anchor, 'error');
            if ($err && $err.length) $err.hide().text('');
            if (!$info || !$info.length) return;

            $info.empty();
            if (withSpinner) {
                $info.append($('<span/>', { 'class': 'ltlb-spinner', 'aria-hidden': 'true' }));
            }
            $info.append(document.createTextNode(String(text || '')));
            $info.show();
        }

        function showInlineError($anchor, text) {
            var $err = getInlineMessageEl($anchor, 'error');
            var $info = getInlineMessageEl($anchor, 'info');
            if ($info && $info.length) $info.hide().empty();
            if (!$err || !$err.length) return;
            $err.text(String(text || '')).show();
        }

        function clearInlineErrorOnly($anchor) {
            if (!$anchor || !$anchor.length) return;
            var $err = getInlineMessageEl($anchor, 'error');
            if ($err && $err.length) {
                $err.hide().text('');
            }
            $anchor.removeAttr('aria-invalid');
        }

        function focusField($field) {
            if (!$field || !$field.length) return;
            try {
                $field.trigger('focus');
            } catch (e) {
                // ignore
            }
        }

        function showStepValidation(stepName) {
            var firstInvalid = null;

            if (stepName === 'service') {
                clearInlineErrorOnly($serviceSelect);
                var serviceId = parseInt($serviceSelect.val() || 0, 10);
                if (!serviceId) {
                    $serviceSelect.attr('aria-invalid', 'true');
                    showInlineError($serviceSelect, 'Please select a service.');
                    firstInvalid = $serviceSelect;
                }
            }

            // Service-mode datetime
            if (stepName === 'datetime' && $dateInput.length && $timeSelect.length) {
                clearInlineErrorOnly($dateInput);
                clearInlineErrorOnly($timeSelect);

                var d = String($dateInput.val() || '');
                var t = String($timeSelect.val() || '');

                if (!d) {
                    $dateInput.attr('aria-invalid', 'true');
                    showInlineError($dateInput, 'Please select a date.');
                    firstInvalid = $dateInput;
                } else if ($timeSelect.prop('disabled')) {
                    // Slots still loading or unavailable
                    showInlineInfo($timeSelect, 'Available times are still loading…', true);
                    firstInvalid = $timeSelect;
                } else if (!t) {
                    $timeSelect.attr('aria-invalid', 'true');
                    showInlineError($timeSelect, 'Please select a time.');
                    firstInvalid = $timeSelect;
                }
            }

            // Hotel-mode dates
            if (stepName === 'datetime' && $checkinInput.length && $checkoutInput.length) {
                clearInlineErrorOnly($checkinInput);
                clearInlineErrorOnly($checkoutInput);

                var ci = String($checkinInput.val() || '');
                var co = String($checkoutInput.val() || '');

                if (!ci) {
                    $checkinInput.attr('aria-invalid', 'true');
                    showInlineError($checkinInput, 'Please select a check-in date.');
                    firstInvalid = $checkinInput;
                } else if (!co) {
                    $checkoutInput.attr('aria-invalid', 'true');
                    showInlineError($checkoutInput, 'Please select a check-out date.');
                    firstInvalid = $checkoutInput;
                } else {
                    var ciD = parseIsoDateLocal(ci);
                    var coD = parseIsoDateLocal(co);
                    if (ciD && ciD < startOfTodayLocal()) {
                        $checkinInput.attr('aria-invalid', 'true');
                        showInlineError($checkinInput, 'The date cannot be in the past.');
                        firstInvalid = $checkinInput;
                    } else if (ciD && coD && coD <= ciD) {
                        $checkoutInput.attr('aria-invalid', 'true');
                        showInlineError($checkoutInput, 'Check-out must be after check-in.');
                        firstInvalid = $checkoutInput;
                    }
                }
            }

            if (stepName === 'details') {
                var $email = $root.find('#ltlb-email');
                if ($email.length) {
                    clearInlineErrorOnly($email);
                    var emailVal = String($email.val() || '').trim();
                    var el = $email.get(0);

                    if (!emailVal) {
                        $email.attr('aria-invalid', 'true');
                        showInlineError($email, 'Please enter your email address.');
                        firstInvalid = $email;
                    } else if (el && typeof el.checkValidity === 'function' && !el.checkValidity()) {
                        $email.attr('aria-invalid', 'true');
                        showInlineError($email, 'Please enter a valid email address.');
                        firstInvalid = $email;
                    }
                }
            }

            if (firstInvalid) {
                focusField(firstInvalid);
                setTimeout(setStepperHeight, 0);
                return false;
            }
            return true;
        }

        function resetTimeSelect(message) {
            if (!$timeSelect.length) return;
            $timeSelect.empty().append($('<option/>', { value: '', text: message }));
            $timeSelect.prop('disabled', true);
        }

        function resetResourceStep() {
            if (!$resourceStep.length) return;
            $resourceSelect.empty().append($('<option/>', { value: '', text: 'Any' }));
            $resourceStep.hide();
            syncStepIndicator();
        }

        function loadHotelAvailability() {
            if (!$checkinInput.length || !$checkoutInput.length) return;
            if (!$resourceStep.length) return;

            var serviceId = parseInt($serviceSelect.val() || 0, 10);
            var checkin = $checkinInput.val();
            var checkout = $checkoutInput.val();
            var guests = parseInt($root.find('#ltlb-guests').val() || 1, 10);
            if (!guests || guests < 1) guests = 1;

            resetResourceStep();
            clearInlineMessages($resourceSelect);

            if (!serviceId || !checkin || !checkout || !restRoot) {
                setTimeout(setStepperHeight, 0);
                return;
            }

            $resourceSelect.prop('disabled', true);
            showInlineInfo($resourceSelect, 'Loading availability…', true);

            $.getJSON(restRoot + '/hotel/availability', { service_id: serviceId, checkin: checkin, checkout: checkout, guests: guests })
                .done(function(resp) {
                    clearInlineMessages($resourceSelect);
                    if (!resp || !Array.isArray(resp.resources)) {
                        setTimeout(setStepperHeight, 0);
                        return;
                    }

                    var selectable = resp.resources.filter(function(r) {
                        if (!r) return false;
                        var available = parseInt(r.available || 0, 10);
                        return available >= guests;
                    });

                    if (selectable.length <= 1) {
                        $resourceStep.hide();
                        $resourceSelect.prop('disabled', false);
                        if (activeStep === 'resource') {
                            setActiveStep('details');
                        }
                        syncStepIndicator();
                        syncNextButtons();
                        setTimeout(setStepperHeight, 0);
                        return;
                    }

                    var anyText = window.ltlbI18n && window.ltlbI18n.any ? window.ltlbI18n.any : 'Any';
                    var roomPrefix = window.ltlbI18n && window.ltlbI18n.room_number ? window.ltlbI18n.room_number : 'Room #';
                    var selectRoomText = window.ltlbI18n && window.ltlbI18n.select_room_optional ? window.ltlbI18n.select_room_optional : 'Optional: select a room.';
                    $resourceSelect.empty().append($('<option/>', { value: '', text: anyText }));
                    selectable.forEach(function(r) {
                        $resourceSelect.append($('<option/>', {
                            value: r.id,
                            text: String(r.name || (roomPrefix + r.id))
                        }));
                    });
                    if ($resourceHint.length) {
                        $resourceHint.text(selectRoomText);
                    }
                    $resourceSelect.prop('disabled', false);
                    $resourceStep.show();
                    syncStepIndicator();
                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);
                })
                .fail(function() {
                    $resourceSelect.prop('disabled', true);
                    var errorText = window.ltlbI18n && window.ltlbI18n.availability_error ? window.ltlbI18n.availability_error : 'Availability could not be loaded. Please try again.';
                    showInlineError($resourceSelect, errorText);
                    setTimeout(setStepperHeight, 0);
                });
        }

        function goForwardIfPossible(fromStep) {
            if (!isValidForStep(fromStep)) return;
            var next = getNextStepName(fromStep);
            if (next) setActiveStep(next);
        }

        function loadTimeSlots() {
            if (!$dateInput.length || !$timeSelect.length) return;

            var serviceId = parseInt($serviceSelect.val() || 0, 10);
            var date = $dateInput.val();

            resetResourceStep();
            clearInlineMessages($timeSelect);

            if (!serviceId || !date) {
                resetTimeSelect('Select a date first');
                syncNextButtons();
                return;
            }

            if (!restRoot) {
                resetTimeSelect('Times could not be loaded');
                showInlineError($timeSelect, 'Times could not be loaded. Please reload the page.');
                syncNextButtons();
                return;
            }

            // Loading state: disable select and show spinner/status (avoid "Loading..." as an option)
            resetTimeSelect('—');
            showInlineInfo($timeSelect, 'Loading available times…', true);

            $.getJSON(restRoot + '/time-slots', { service_id: serviceId, date: date })
                .done(function(slots) {
                    clearInlineMessages($timeSelect);
                    $timeSelect.empty();

                    if (!Array.isArray(slots) || slots.length === 0) {
                        $timeSelect.append($('<option/>', { value: '', text: 'No times available' }));
                        $timeSelect.prop('disabled', true);
                        showInlineInfo($timeSelect, 'No times are available for this date.', false);
                        syncNextButtons();
                        return;
                    }

                    $timeSelect.append($('<option/>', { value: '', text: 'Select a time' }));
                    for (var i = 0; i < slots.length; i++) {
                        var slot = slots[i] || {};
                        var label = String(slot.time || '');
                        if (slot.spots_left && parseInt(slot.spots_left, 10) > 0) {
                            label += ' (' + slot.spots_left + ')';
                        }

                        var $opt = $('<option/>', { value: slot.time || '', text: label });
                        if (slot.start) $opt.attr('data-start', slot.start);
                        if (slot.free_resources_count != null) $opt.attr('data-free', slot.free_resources_count);
                        $timeSelect.append($opt);
                    }

                    $timeSelect.prop('disabled', false);

                    // Apply optional prefilled time once slots exist
                    if (prefillTime && !$timeSelect.val()) {
                        var has = $timeSelect.find('option[value="' + String(prefillTime).replace(/"/g, '\\"') + '"]').length;
                        if (has) {
                            $timeSelect.val(String(prefillTime));
                            prefillTime = '';
                            onTimeChanged();
                        }
                    }

                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);
                })
                .fail(function() {
                    resetTimeSelect('—');
                    showInlineError($timeSelect, 'Times could not be loaded. Please try again.');
                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);
                });
        }

        function loadResourcesForSelectedSlot() {
            if (!$resourceStep.length) return;
            var serviceId = parseInt($serviceSelect.val() || 0, 10);
            var $selected = $timeSelect.find('option:selected');
            var start = $selected.attr('data-start') || '';

            resetResourceStep();
            clearInlineMessages($resourceSelect);

            if (!serviceId || !start || !restRoot) {
                return;
            }

            $resourceSelect.prop('disabled', true);
            showInlineInfo($resourceSelect, 'Loading resources…', true);

            $.getJSON(restRoot + '/slot-resources', { service_id: serviceId, start: start })
                .done(function(resp) {
                    clearInlineMessages($resourceSelect);
                    $resourceSelect.prop('disabled', false);
                    if (!resp || !Array.isArray(resp.resources)) return;

                    var selectable = resp.resources.filter(function(r) {
                        return r && parseInt(r.available || 0, 10) > 0;
                    });

                    if (selectable.length <= 1) {
                        $resourceStep.hide();
                        syncStepIndicator();
                        syncNextButtons();
                        setTimeout(setStepperHeight, 0);
                        // Skip resource step entirely
                        if (activeStep === 'datetime') {
                            setActiveStep('details');
                            var $email = $root.find('#ltlb-email');
                            if ($email.length) $email.trigger('focus');
                        }
                        return;
                    }

                    var anyText = window.ltlbI18n && window.ltlbI18n.any ? window.ltlbI18n.any : 'Any';
                    var resourcePrefix = window.ltlbI18n && window.ltlbI18n.resource_number ? window.ltlbI18n.resource_number : 'Resource #';
                    var selectResourceText = window.ltlbI18n && window.ltlbI18n.select_resource_optional ? window.ltlbI18n.select_resource_optional : 'Optional: select a resource.';
                    $resourceSelect.empty().append($('<option/>', { value: '', text: anyText }));
                    selectable.forEach(function(r) {
                        $resourceSelect.append($('<option/>', {
                            value: r.id,
                            text: String(r.name || (resourcePrefix + r.id))
                        }));
                    });
                    if ($resourceHint.length) {
                        $resourceHint.text(selectResourceText);
                    }
                    $resourceStep.show();
                    syncStepIndicator();
                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);

                    // If calendar-start or normal wizard: move into resource window
                    if (activeStep === 'datetime') {
                        setActiveStep('resource');
                    }
                })
                .fail(function() {
                    $resourceSelect.prop('disabled', true);
                    var errorText = window.ltlbI18n && window.ltlbI18n.resources_error ? window.ltlbI18n.resources_error : 'Resources could not be loaded. Please try again.';
                    showInlineError($resourceSelect, errorText);
                    setTimeout(setStepperHeight, 0);
                });
        }

        function onTimeChanged() {
            syncNextButtons();
            if ($timeSelect.val()) {
                loadResourcesForSelectedSlot();
                // If resource step stays hidden, we auto-advance to details.
                if (!$resourceStep.is(':visible')) {
                    setActiveStep('details');
                    var $email = $root.find('#ltlb-email');
                    if ($email.length) $email.trigger('focus');
                }
            }
        }

        // --- Stepper init + bindings ---
        // Apply service prefill (calendar mode or normal wizard attribute)
        if (prefillServiceId && (!$serviceSelect.val() || parseInt($serviceSelect.val(), 10) !== prefillServiceId)) {
            $serviceSelect.val(String(prefillServiceId));
        }

        // Initial active step
        var serviceStepHidden = $panels.filter('[data-ltlb-step="service"]').css('display') === 'none';
        if (startMode === 'calendar' && $dateInput.length && $timeSelect.length) {
            setActiveStep('datetime');
        } else if (serviceStepHidden) {
            setActiveStep('datetime');
        } else {
            setActiveStep('service');
        }

        // Back/Next buttons
        $root.on('click', '[data-ltlb-back]', function() {
            var $panel = $(this).closest('[data-ltlb-step]');
            var stepName = String($panel.data('ltlb-step'));
            var prev = getPrevStepName(stepName);
            if (prev) setActiveStep(prev);
            syncNextButtons();
            updateWizardProgress();
        });
        
        function updateWizardProgress() {
            var $steps = $root.find('[data-ltlb-step]');
            var $visible = $steps.filter(':visible');
            var currentIndex = $steps.index($visible) + 1;
            var totalSteps = $steps.length;
            
            $root.find('.ltlb-wizard-current-step').text(currentIndex);
            $root.find('.ltlb-wizard-total-steps').text(totalSteps);
        }

        $root.on('click', '[data-ltlb-next]', function() {
            var $panel = $(this).closest('[data-ltlb-step]');
            var stepName = String($panel.data('ltlb-step'));
            if (!isValidForStep(stepName)) {
                showStepValidation(stepName);
                syncNextButtons();
                return;
            }
            
            // Update wizard progress counter
            updateWizardProgress();

            // Service mode: after datetime step, decide if resource step needed
            if (stepName === 'datetime' && $dateInput.length && $timeSelect.length) {
                if ($resourceStep.is(':visible')) {
                    setActiveStep('resource');
                } else {
                    setActiveStep('details');
                    var $email = $root.find('#ltlb-email');
                    if ($email.length) $email.trigger('focus');
                }
                syncNextButtons();
                return;
            }

            goForwardIfPossible(stepName);
            syncNextButtons();
        });

        // Auto-advance: service selection
        $serviceSelect.on('change', function() {
            clearInlineErrorOnly($serviceSelect);
            syncNextButtons();
            if (parseInt($serviceSelect.val() || 0, 10)) {
                // In service mode, slot loading depends on service+date
                if ($dateInput.length && $timeSelect.length) {
                    resetResourceStep();
                    loadTimeSlots();
                }
				// In hotel mode, room list depends on service+dates+guests
				if ($checkinInput.length && $checkoutInput.length) {
					loadHotelAvailability();
				}
                setActiveStep('datetime');
                setTimeout(function(){
                    // Try to open the date picker right away when advancing
                    if ($dateInput.length) $dateInput.trigger('focus');
                    if ($checkinInput.length) $checkinInput.trigger('focus');
                }, 0);
            }
        });

        // Service-mode datetime: load slots
        if ($dateInput.length && $timeSelect.length) {
            resetResourceStep();
            loadTimeSlots();
            $dateInput.on('change', function() {
                clearInlineErrorOnly($dateInput);
                resetResourceStep();
                loadTimeSlots();
                syncNextButtons();
            });
            $timeSelect.on('change', function() {
                clearInlineErrorOnly($timeSelect);
                onTimeChanged();
            });
        }

        // Hotel-mode datetime validation
        if ($checkinInput.length && $checkoutInput.length) {
            var syncHotel = function() {
                clearInlineErrorOnly($checkinInput);
                clearInlineErrorOnly($checkoutInput);
                syncNextButtons();
                updatePricePreview();
				loadHotelAvailability();
            };

            $checkinInput.on('change', syncHotel);
            $checkoutInput.on('change', syncHotel);
            $root.find('#ltlb-guests').on('input change', syncHotel);
        }

        // Details validation
        $root.find('#ltlb-email').on('input change', function() {
            clearInlineErrorOnly($(this));
            syncNextButtons();
        });

        // Initial sync
        syncNextButtons();
        syncStepIndicator();
        setStepperHeight();

        // Keep window responsive if fonts/layout change
        $(window).on('resize', function() {
            setTimeout(setStepperHeight, 0);
        });
    }

    $(function() {
        $('.ltlb-booking').each(function() {
            initWidget($(this));
        });
    });

})(jQuery);
