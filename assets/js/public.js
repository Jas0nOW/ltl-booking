jQuery(document).ready(function($) {
    var serviceSelect = $('select[name="service_id"]');
    var dateSelect = $('input[name="date"]');
    var timeSelect = $('select[name="time_slot"]');

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
                            timeSelect.append($('<option>', {
                                value: formattedTime,
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

    // Initial fetch
    fetchTimeSlots();
});
