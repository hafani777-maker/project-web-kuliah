/* notif.js
   Menampilkan notifikasi (toast).
   - Otomatis menampilkan notifikasi dari session (data-notif di #notifBox).
   - Bisa dipanggil manual: tampilNotif('Pesan', 'sukses', 'cart.php', 'Lihat Troli'); */

(function () {

    var box = document.getElementById('notifBox');
    if (!box) return;

    var ikon = { sukses: '✓', info: 'i', peringatan: '!', error: '✕' };
    var kurangGerak = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function tutup(el) {
        if (!el || !el.parentNode) return;
        el.classList.add('keluar');
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 260);
    }

    window.tampilNotif = function (pesan, tipe, link, label) {

        tipe = ikon[tipe] ? tipe : 'info';

        var el = document.createElement('div');
        el.className = 'notif ' + tipe;

        var ic = document.createElement('div');
        ic.className = 'notif-icon';
        ic.setAttribute('aria-hidden', 'true');
        ic.textContent = ikon[tipe];

        var body = document.createElement('div');
        body.className = 'notif-body';

        var p = document.createElement('div');
        p.className = 'notif-pesan';
        p.textContent = pesan;          // textContent: aman dari XSS
        body.appendChild(p);

        // Hanya izinkan link biasa (bukan javascript:)
        if (link && !/^\s*javascript:/i.test(link)) {
            var a = document.createElement('a');
            a.className = 'notif-link';
            a.href = link;
            a.textContent = label || 'Lihat';
            body.appendChild(a);
        }

        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'notif-close';
        x.setAttribute('aria-label', 'Tutup notifikasi');
        x.innerHTML = '&times;';
        x.addEventListener('click', function () { tutup(el); });

        var bar = document.createElement('div');
        bar.className = 'notif-bar';

        el.appendChild(ic);
        el.appendChild(body);
        el.appendChild(x);
        el.appendChild(bar);
        box.appendChild(el);

        // Tutup otomatis saat garis waktu habis (berhenti sementara saat di-hover)
        if (kurangGerak) {
            setTimeout(function () { tutup(el); }, 6000);
        } else {
            bar.addEventListener('animationend', function () { tutup(el); });
        }
    };

    // Notifikasi dari session (hasil set_notif di PHP)
    var awal = box.getAttribute('data-notif');

    if (awal) {
        try {
            var d = JSON.parse(awal);
            window.tampilNotif(d.pesan, d.tipe, d.link, d.label);
        } catch (e) {
            console.log('Notifikasi tidak valid:', e);
        }
    }

})();