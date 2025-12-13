/**
 * LazyBookings Public JS
 * Handles dynamic resource dropdown and hotel price preview
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('LazyBookings public.js loaded');

        // Hotel mode: price preview
        var $serviceSelect = $('select[name="service_id"]');
        var $checkinInput = $('#ltlb-checkin');
        var $checkoutInput = $('#ltlb-checkout');
        var $pricePreview = $('#ltlb-price-preview');
        var $priceAmount = $('#ltlb-price-amount');
        var $priceBreakdown = $('#ltlb-price-breakdown');

        function updatePricePreview() {
            if (!$pricePreview.length) return; // not hotel mode

            var selectedOption = $serviceSelect.find('option:selected');
            var priceCents = parseInt(selectedOption.data('price') || 0);
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
    });

})(jQuery);
