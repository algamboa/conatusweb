// Magnific Popup
$('.mfp-image').magnificPopup({
    type: 'image',
    closeOnContentClick: true,
    mainClass: 'mfp-fade',
    gallery: {
        enabled: true,
        navigateByImgClick: true,
        preload: [0, 1]
    }
});

// Portfolio filter (stable Bootstrap-based filtering)
$(window).on('load', function () {
    var $container = $('.projects-wrapper');
    var $filter = $('#filter');
    if (!$container.length || !$filter.length) {
        return;
    }

    var $items = $container.find('.spacing');

    $filter.find('a').on('click', function () {
        var selector = $(this).attr('data-filter') || '*';

        $filter.find('a').removeClass('active');
        $(this).addClass('active');

        if (selector === '*') {
            $items.removeClass('d-none');
            return false;
        }

        $items.each(function () {
            var $item = $(this);
            if ($item.is(selector)) {
                $item.removeClass('d-none');
            } else {
                $item.addClass('d-none');
            }
        });

        return false;
    });
});