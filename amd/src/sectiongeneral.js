define(['jquery'], function($) {

    const init = function(rootSelector) {
        const $root = $(rootSelector);

        if (!$root.length || $root.data('edukavChronoZoomBound') === 1) {
            return;
        }

        $root.data('edukavChronoZoomBound', 1);

        const $scope = $root.find('.edukav-general-chrono-text');
        const $overlay = $root.find('.edukav-chrono-zoom-overlay');
        const $overlayImage = $overlay.find('.edukav-chrono-zoom-image');
        const $overlayClose = $overlay.find('.edukav-chrono-zoom-close');

        if (!$scope.length || !$overlay.length || !$overlayImage.length || !$overlayClose.length) {
            return;
        }

        const closeOverlay = () => {
            $overlay.prop('hidden', true);
            $overlay.attr('aria-hidden', 'true');
            $('body').removeClass('edukav-zoom-open');
        };

        const openOverlay = (src, alt) => {
            if (!src) {
                return;
            }

            $overlayImage.attr('src', src);
            $overlayImage.attr('alt', alt || '');
            $overlay.prop('hidden', false);
            $overlay.attr('aria-hidden', 'false');
            $('body').addClass('edukav-zoom-open');
        };

        $scope.find('img').each(function() {
            const $img = $(this);

            $img.css('cursor', 'zoom-in');

            $img.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openOverlay(this.currentSrc || this.src, this.alt || '');
            });
        });

        $overlay.on('click', function(e) {
            if (e.target === this) {
                closeOverlay();
            }
        });

        $overlayClose.on('click', closeOverlay);

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && !$overlay.prop('hidden')) {
                closeOverlay();
            }
        });
    };

    return {
        init: init
    };
});
