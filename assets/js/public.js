jQuery(document).ready(function($) {
    var serviceSelect = $('select[name="service_id"]');
    var dateSelect = $('input[name="date"]');
    var timeSelect = $('select[name="time_slot"]');
    var resourceSelect = $('#ltlb_resource_select');

    function fetchTimeSlots() {
        var serviceId = serviceSelect.val();
        var date = dateSelect.val();

        if (!serviceId || !date) {
            return;
        }

        $.ajax({
            url: '/wp-json/ltlb/v1/time-slots',
            data: {
                service_id: serviceId,
                date: date
            },
            success: function(response) {
                timeSelect.empty();
                if (Object.keys(response).length > 0) {
                    $.each(response, function(staffId, slots) {
                        $.each(slots, function(index, slot) {
                            var startTime = new Date(slot.start);
                            var formattedTime = startTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            // use full datetime as value
                            timeSelect.append($('<option>', {
                                value: slot.start,
                                'data-end': slot.end,
                                text: formattedTime
                            }));
                        });
                    });
                } else {
                    timeSelect.append($('<option>', {
                        value: '',
                        text: 'No available time slots'
                    }));
                }
            },
            error: function() {
                timeSelect.empty();
                timeSelect.append($('<option>', {
                    value: '',
                    text: 'Error fetching time slots'
                }));
            }
        });
    }

    serviceSelect.on('change', fetchTimeSlots);
    dateSelect.on('change', fetchTimeSlots);

    // When a slot is selected, fetch available resources for that slot
    timeSelect.on('change', function() {
        var val = $(this).val();
        if (!val) {
            resourceSelect.hide().empty();
            return;
        }

        var serviceId = serviceSelect.val();
        var start = val; // full datetime string
        $.ajax({
            url: '/wp-json/ltlb/v1/slot-resources',
            data: { service_id: serviceId, start: start },
            success: function(resp) {
                resourceSelect.empty();
                if (resp && resp.resources && resp.resources.length > 1) {
                    // show dropdown when more than 1 free resource exists
                    $.each(resp.resources, function(i, r) {
                        if (r.available > 0) {
                            resourceSelect.append($('<option>', { value: r.id, text: r.name + ' (' + r.available + ' free)' }));
                        }
                    });
                    if (resourceSelect.children().length > 0) {
                        resourceSelect.show();
                    } else {
                        resourceSelect.hide();
                    }
                } else {
                    resourceSelect.hide();
                }
            },
            error: function() {
                resourceSelect.hide().empty();
            }
        });
    });

    // Initial fetch
    fetchTimeSlots();
});
