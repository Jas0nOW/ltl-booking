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

            // Animate height changes by setting explicit height.
            var current = $stepper.height();
            if (!current || current <= 0) {
                $stepper.css('height', h + 'px');
                return;
            }
            if (Math.abs(current - h) < 2) {
                $stepper.css('height', h + 'px');
                return;
            }

            // Set current height first, then transition to new height.
            $stepper.css('height', current + 'px');
            window.requestAnimationFrame(function() {
                $stepper.css('height', h + 'px');
            });
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

                    $priceAmount.text('€' + total);
                    $priceBreakdown.text(nights + ' night' + (nights !== 1 ? 's' : '') + ' × €' + pricePerNight);
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

        function resetTimeSelect(message) {
            if (!$timeSelect.length) return;
            $timeSelect.empty().append($('<option/>', { value: '', text: message }));
            $timeSelect.prop('disabled', true);
        }

        function resetResourceStep() {
            if (!$resourceStep.length) return;
            $resourceSelect.empty().append($('<option/>', { value: '', text: 'Any available' }));
            $resourceStep.hide();
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

            if (!serviceId || !checkin || !checkout || !restRoot) {
                setTimeout(setStepperHeight, 0);
                return;
            }

            $.getJSON(restRoot + '/hotel/availability', { service_id: serviceId, checkin: checkin, checkout: checkout, guests: guests })
                .done(function(resp) {
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
                        if (activeStep === 'resource') {
                            setActiveStep('details');
                        }
                        syncNextButtons();
                        setTimeout(setStepperHeight, 0);
                        return;
                    }

                    $resourceSelect.empty().append($('<option/>', { value: '', text: 'Any available' }));
                    selectable.forEach(function(r) {
                        $resourceSelect.append($('<option/>', {
                            value: r.id,
                            text: String(r.name || ('Room #' + r.id))
                        }));
                    });
                    if ($resourceHint.length) {
                        $resourceHint.text('Choose a preferred room (optional).');
                    }
                    $resourceStep.show();
                    syncNextButtons();
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

            if (!serviceId || !date) {
                resetTimeSelect('Select date first');
                syncNextButtons();
                return;
            }

            if (!restRoot) {
                resetTimeSelect('Unable to load slots');
                syncNextButtons();
                return;
            }

            resetTimeSelect('Loading…');

            $.getJSON(restRoot + '/time-slots', { service_id: serviceId, date: date })
                .done(function(slots) {
                    $timeSelect.empty();

                    if (!Array.isArray(slots) || slots.length === 0) {
                        $timeSelect.append($('<option/>', { value: '', text: 'No slots available' }));
                        $timeSelect.prop('disabled', true);
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
                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);
                })
                .fail(function() {
                    resetTimeSelect('Unable to load slots');
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

            if (!serviceId || !start || !restRoot) {
                return;
            }

            $.getJSON(restRoot + '/slot-resources', { service_id: serviceId, start: start })
                .done(function(resp) {
                    if (!resp || !Array.isArray(resp.resources)) return;

                    var selectable = resp.resources.filter(function(r) {
                        return r && parseInt(r.available || 0, 10) > 0;
                    });

                    if (selectable.length <= 1) {
                        $resourceStep.hide();
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

                    $resourceSelect.empty().append($('<option/>', { value: '', text: 'Any available' }));
                    selectable.forEach(function(r) {
                        $resourceSelect.append($('<option/>', {
                            value: r.id,
                            text: String(r.name || ('Resource #' + r.id))
                        }));
                    });
                    if ($resourceHint.length) {
                        $resourceHint.text('Choose a preferred resource (optional).');
                    }
                    $resourceStep.show();
                    syncNextButtons();
                    setTimeout(setStepperHeight, 0);

                    // If calendar-start or normal wizard: move into resource window
                    if (activeStep === 'datetime') {
                        setActiveStep('resource');
                    }
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
        });

        $root.on('click', '[data-ltlb-next]', function() {
            var $panel = $(this).closest('[data-ltlb-step]');
            var stepName = String($panel.data('ltlb-step'));
            if (!isValidForStep(stepName)) {
                // Trigger native validation UI if possible
                try {
                    var formEl = $root.find('form').get(0);
                    if (formEl && typeof formEl.reportValidity === 'function') {
                        formEl.reportValidity();
                    }
                } catch (e) {
                    // ignore
                }
                syncNextButtons();
                return;
            }

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
                resetResourceStep();
                loadTimeSlots();
                syncNextButtons();
            });
            $timeSelect.on('change', onTimeChanged);
        }

        // Hotel-mode datetime validation
        if ($checkinInput.length && $checkoutInput.length) {
            var syncHotel = function() {
                syncNextButtons();
                updatePricePreview();
				loadHotelAvailability();
            };

            $checkinInput.on('change', syncHotel);
            $checkoutInput.on('change', syncHotel);
            $root.find('#ltlb-guests').on('input change', syncHotel);
        }

        // Details validation
        $root.find('#ltlb-email').on('input change', syncNextButtons);

        // Initial sync
        syncNextButtons();
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
