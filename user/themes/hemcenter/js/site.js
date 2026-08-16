/* Мобильное меню и карусель на главной. Больше JS на сайте нет. */
(function () {
    'use strict';

    /* ------------------------------------------------------------ меню */

    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.hasAttribute('data-open');

            if (open) {
                nav.removeAttribute('data-open');
            } else {
                nav.setAttribute('data-open', '');
            }

            toggle.setAttribute('aria-expanded', String(!open));
        });
    }

    /* --------------------------------------------------------- карусель */

    var track = document.querySelector('[data-carousel]');

    if (!track) {
        return;
    }

    var slides = Array.prototype.slice.call(track.querySelectorAll('.hero__slide'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('.hero__dot'));

    if (slides.length < 2) {
        return;
    }

    var index = 0;
    var timer = null;
    var INTERVAL = 6000;

    function show(next) {
        index = (next + slides.length) % slides.length;

        slides.forEach(function (slide, i) {
            if (i === index) {
                slide.setAttribute('data-active', '');
            } else {
                slide.removeAttribute('data-active');
            }
        });

        dots.forEach(function (dot, i) {
            dot.setAttribute('aria-selected', String(i === index));
        });
    }

    function start() {
        stop();
        timer = setInterval(function () { show(index + 1); }, INTERVAL);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            show(parseInt(dot.getAttribute('data-slide'), 10));
            start();
        });
    });

    // Стрелки: листать кликом, не дожидаясь автосмены
    Array.prototype.forEach.call(track.querySelectorAll('[data-step]'), function (arrow) {
        arrow.addEventListener('click', function () {
            show(index + parseInt(arrow.getAttribute('data-step'), 10));
            start();
        });
    });

    // С клавиатуры — когда фокус внутри баннера
    track.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { show(index - 1); start(); }
        if (e.key === 'ArrowRight') { show(index + 1); start(); }
    });

    // Свайп на телефоне
    var touchX = null;

    track.addEventListener('touchstart', function (e) {
        touchX = e.changedTouches[0].clientX;
    }, { passive: true });

    track.addEventListener('touchend', function (e) {
        if (touchX === null) { return; }

        var dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 45) { show(index + (dx < 0 ? 1 : -1)); start(); }
        touchX = null;
    }, { passive: true });

    // Не крутим, когда вкладка скрыта или курсор на баннере
    track.addEventListener('mouseenter', stop);
    track.addEventListener('mouseleave', start);

    document.addEventListener('visibilitychange', function () {
        document.hidden ? stop() : start();
    });

    // Уважаем системную настройку «меньше движения»
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        start();
    }
})();
