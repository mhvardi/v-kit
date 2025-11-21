(function($) {
    "use strict";

    var VardiSeoBoxHandler = function($scope, $) {
        var $widget = $scope.find('.seo-content-box');
        if ($widget.length === 0) {
            return;
        }

        var $box = $widget.find('.seo-content-box-text');
        var $toggleBtn = $widget.find('.seo-content-toggle');
        
        if ($widget.data('vardi-initialized')) {
            return;
        }
        $widget.data('vardi-initialized', true);

        var getResponsiveHeight = function() {
            var windowWidth = window.innerWidth;
            var desktopHeight = $widget.data('desktop-height') || 150;
            var tabletHeight = $widget.data('tablet-height') || desktopHeight;
            var mobileHeight = $widget.data('mobile-height') || tabletHeight;

            if (windowWidth <= 767 && mobileHeight) { return mobileHeight; }
            if (windowWidth <= 1024 && tabletHeight) { return tabletHeight; }
            return desktopHeight;
        };

        var setCollapsedHeight = function() {
            if ($widget.hasClass('is-expanded')) {
                return;
            }
            var collapsedHeight = getResponsiveHeight();
            $box.css('max-height', collapsedHeight + 'px');
        };

        setTimeout(setCollapsedHeight, 50);

        var resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(setCollapsedHeight, 250);
        });

        $toggleBtn.on('click keypress', function(e) {
            if (e.type === 'keypress' && (e.which !== 13 && e.which !== 32)) {
                return;
            }
            e.preventDefault();

            var isExpanded = $widget.hasClass('is-expanded');
            var $iconSpan = $toggleBtn.find('.seo-toggle-icon');
            var $textSpan = $toggleBtn.find('.seo-toggle-text');

            if (isExpanded) {
                var collapsedHeight = getResponsiveHeight();
                $box.css('max-height', $box[0].scrollHeight + 'px');
                setTimeout(function() {
                    $box.css('max-height', collapsedHeight + 'px');
                }, 10);

                $widget.removeClass('is-expanded');
                $box.addClass('collapsed');
                $toggleBtn.attr('aria-expanded', 'false');
                $textSpan.text($toggleBtn.data('more-text'));
                try { $iconSpan.html(atob($toggleBtn.data('icon-more-html'))); } catch (err) {}
            } else {
                var scrollHeight = $box[0].scrollHeight;
                $box.css('max-height', scrollHeight + 'px');
                $widget.addClass('is-expanded');
                $box.removeClass('collapsed');
                $toggleBtn.attr('aria-expanded', 'true');
                $textSpan.text($toggleBtn.data('less-text'));
                try { $iconSpan.html(atob($toggleBtn.data('icon-less-html'))); } catch (err) {}
                
                $box.one('transitionend', function() {
                    if ($widget.hasClass('is-expanded')) {
                        $(this).css('max-height', 'none');
                    }
                });
            }
        });
    };

    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/seo_content_box.default', VardiSeoBoxHandler);
    });

})(jQuery);