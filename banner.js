/* banner.js
   Carousel banner promo: otomatis geser, bisa diklik, berhenti saat disentuh. */

(function () {

    var hero = document.getElementById('promoHero');
    if (!hero) return;

    var slides = hero.querySelectorAll('.promo-slide');
    var dotsBox = hero.querySelector('.promo-dots');
    var jeda = 1000;
    var index = 0;
    var timer = null;
    var reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (slides.length < 2) {
        hero.querySelectorAll('.promo-nav, .promo-dots').forEach(function (el) {
            el.style.display = 'none';
        });
        return;
    }

    // Buat titik navigasi
    var dots = [];
    slides.forEach(function (_, i) {
        var d = document.createElement('button');
        d.type = 'button';
        d.className = 'promo-dot';
        d.setAttribute('aria-label', 'Banner ' + (i + 1));
        d.addEventListener('click', function () { tampil(i); mulai(); });
        dotsBox.appendChild(d);
        dots.push(d);
    });

    function tampil(i) {
        index = (i + slides.length) % slides.length;

        slides.forEach(function (s, n) {
            s.classList.toggle('is-active', n === index);
            s.setAttribute('aria-hidden', n === index ? 'false' : 'true');
        });

        dots.forEach(function (d, n) {
            d.classList.toggle('is-active', n === index);
        });
    }

    function mulai() {
        berhenti();
        if (reduceMotion) return;
        timer = setInterval(function () { tampil(index + 1); }, jeda);
    }

    function berhenti() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    hero.querySelector('.promo-nav.prev').addEventListener('click', function () {
        tampil(index - 1); mulai();
    });

    hero.querySelector('.promo-nav.next').addEventListener('click', function () {
        tampil(index + 1); mulai();
    });

    hero.addEventListener('mouseenter', berhenti);
    hero.addEventListener('mouseleave', mulai);
    hero.addEventListener('focusin', berhenti);
    hero.addEventListener('focusout', mulai);

    // Geser dengan jari (mobile)
    var startX = null;

    hero.addEventListener('touchstart', function (e) {
        startX = e.touches[0].clientX;
    }, { passive: true });

    hero.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var selisih = e.changedTouches[0].clientX - startX;
        if (Math.abs(selisih) > 50) {
            tampil(index + (selisih < 0 ? 1 : -1));
            mulai();
        }
        startX = null;
    }, { passive: true });

    tampil(0);
    mulai();

})();