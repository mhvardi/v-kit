(function($) {
    "use strict";

    var VardiFaqHandler = function($scope, $) {
        var $widgetWrapper = $scope.find('.vardi-faq-wrapper');
        if ($widgetWrapper.length === 0) {
            return;
        }

        if ($widgetWrapper.data('vardi-faq-initialized')) {
            return;
        }
        $widgetWrapper.data('vardi-faq-initialized', true);

        // خواندن تنظیمات از data attributes
        const isAccordion = $widgetWrapper.data('accordion-mode') === 'yes';
        // دریافت سرعت انیمیشن از PHP (پیش‌فرض: 400ms)
        const animationSpeed = $widgetWrapper.data('animation-speed') || 400;

        const allItems = $widgetWrapper.find('.vardi-faq-item');

        // مهم: مدیریت آیتم‌هایی که در ابتدا باز هستند
        // ما فقط به jQuery می‌گوییم که آنها را بدون انیمیشن 'show' کند
        // CSS آنها را 'display: block' می‌کند
        allItems.filter('.vardi-item-active').find('.vardi-faq-answer-wrapper').show();


        allItems.each(function() {
            const item = $(this); // .vardi-faq-item
            const summary = item.find('.vardi-faq-question'); // .vardi-faq-question
            const content = item.find('.vardi-faq-answer-wrapper'); // .vardi-faq-answer-wrapper

            summary.on('click', function() {

                if (!isAccordion) {
                    // --- حالت تاگل (Toggle) ---
                    // کلاس را برای آیکن toggle کن
                    item.toggleClass('vardi-item-active');
                    // انیمیشن نرم jQuery
                    content.slideToggle(animationSpeed);

                } else {
                    // --- حالت آکاردئون (Accordion) ---
                    const wasActive = item.hasClass('vardi-item-active');

                    // 1. بستن همه آیتم‌های دیگر (به جز این یکی)
                    allItems.not(item).removeClass('vardi-item-active');
                    allItems.not(item).find('.vardi-faq-answer-wrapper').slideUp(animationSpeed);

                    // 2. مدیریت آیتم فعلی
                    if (wasActive) {
                        // اگر باز بود، آن را ببند
                        item.removeClass('vardi-item-active');
                        content.slideUp(animationSpeed);
                    } else {
                        // اگر بسته بود، آن را باز کن
                        item.addClass('vardi-item-active');
                        content.slideDown(animationSpeed);
                    }
                }
            });
        });
    };

    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/vardi-faq.default', VardiFaqHandler);
    });

})(jQuery);