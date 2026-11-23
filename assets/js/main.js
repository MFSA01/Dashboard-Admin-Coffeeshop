/* =========================================================
   B L E N D — main.js
   Vanilla JS: init Lucide, auto-close alert, konfirmasi hapus,
   preview upload gambar, live hitung total harga transaksi.
   ========================================================= */

(function () {
  'use strict';

  var DEFAULT_IMAGE = 'assets/img/default-coffee.svg';

  /* -------------------------------------------------------
     1. Inisialisasi ikon Lucide
     ------------------------------------------------------- */
  function initIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  /* -------------------------------------------------------
     2. Auto-close alert flash message
     ------------------------------------------------------- */
  function initAlerts() {
    var alerts = document.querySelectorAll('[data-flash-alert]');

    Array.prototype.forEach.call(alerts, function (alert) {
      var closeBtn = alert.querySelector('[data-alert-close]');

      var hide = function () {
        if (alert.classList.contains('is-hiding')) {
          return;
        }
        alert.classList.add('is-hiding');
        window.setTimeout(function () {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 380);
      };

      if (closeBtn) {
        closeBtn.addEventListener('click', hide);
      }

      window.setTimeout(hide, 6000);
    });
  }

  /* -------------------------------------------------------
     3. Konfirmasi hapus data
     ------------------------------------------------------- */
  function initDeleteConfirm() {
    var triggers = document.querySelectorAll('[data-confirm-delete]');

    Array.prototype.forEach.call(triggers, function (el) {
      el.addEventListener('click', function (event) {
        var pesan = el.getAttribute('data-confirm-delete') || 'Yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';

        if (!window.confirm(pesan)) {
          event.preventDefault();
          return false;
        }

        return true;
      });
    });
  }

  /* -------------------------------------------------------
     4. Preview upload gambar
     ------------------------------------------------------- */
  function initImagePreview() {
    var wrappers = document.querySelectorAll('[data-upload-field]');

    Array.prototype.forEach.call(wrappers, function (wrapper) {
      var input = wrapper.querySelector('[data-upload-input]');
      var preview = wrapper.querySelector('[data-upload-preview]');
      var fileName = wrapper.querySelector('[data-upload-filename]');

      if (!input || !preview) {
        return;
      }

      input.addEventListener('change', function () {
        var file = input.files && input.files[0];

        if (!file) {
          preview.src = preview.getAttribute('data-default-src') || DEFAULT_IMAGE;
          if (fileName) {
            fileName.textContent = 'Belum ada file dipilih.';
          }
          return;
        }

        if (!file.type || file.type.indexOf('image/') !== 0) {
          window.alert('Berkas harus berupa gambar (jpg, jpeg, png, webp).');
          input.value = '';
          return;
        }

        if (file.size > 2 * 1024 * 1024) {
          window.alert('Ukuran gambar maksimal 2 MB.');
          input.value = '';
          return;
        }

        var reader = new FileReader();

        reader.addEventListener('load', function (event) {
          preview.src = String(event.target.result);
          preview.classList.remove('is-broken');
        });

        reader.readAsDataURL(file);

        if (fileName) {
          fileName.textContent = file.name;
        }
      });

      input.addEventListener('error', function () {
        if (preview) {
          preview.src = DEFAULT_IMAGE;
        }
      });

      /* Sinkronkan pratinjau dengan foto cup kategori saat kategori diganti. */
      var petaCup = wrapper.getAttribute('data-cup-images');
      var radioKategori = document.querySelectorAll('input[name="kategori"]');

      if (!petaCup || !radioKategori.length) {
        return;
      }

      var daftarCup = {};

      try {
        daftarCup = JSON.parse(petaCup) || {};
      } catch (err) {
        return;
      }

      Array.prototype.forEach.call(radioKategori, function (radio) {
        radio.addEventListener('change', function () {
          if (input.files && input.files.length) {
            return;
          }

          var berkas = daftarCup[radio.value];

          if (!berkas) {
            return;
          }

          var url = 'uploads/menu/' + encodeURIComponent(berkas);
          preview.src = url;
          preview.setAttribute('data-default-src', url);
        });
      });
    });
  }

  /* -------------------------------------------------------
     5. Live hitung total harga transaksi
     ------------------------------------------------------- */
  function initHitungTotal() {
    var select = document.querySelector('[data-harga-source]');

    if (!select) {
      return;
    }

    var jumlahInput = document.querySelector('[data-jumlah-input]');
    var hargaSatuanEl = document.querySelector('[data-harga-satuan]');
    var totalEl = document.querySelector('[data-total-harga]');
    var stokInfoEl = document.querySelector('[data-stok-info]');
    var ringkasanTotal = document.querySelector('[data-ringkasan-total]');
    var ringkasanProduk = document.querySelector('[data-ringkasan-produk]');
    var ringkasanHarga = document.querySelector('[data-ringkasan-harga]');
    var ringkasanJumlah = document.querySelector('[data-ringkasan-jumlah]');
    var ringkasanStok = document.querySelector('[data-ringkasan-stok]');

    function formatAngka(angka) {
      var nilai = Math.max(0, Math.round(Number(angka) || 0));
      return nilai.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatRupiah(angka) {
      return 'Rp ' + formatAngka(angka);
    }

    function formatNilai(el, angka) {
      return el && el.getAttribute('data-format') === 'angka'
        ? formatAngka(angka)
        : formatRupiah(angka);
    }

    function tampilkan(el, teks) {
      if (!el) {
        return;
      }

      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        el.value = teks;
      } else {
        el.textContent = teks;
      }
    }

    function ambilData() {
      var opt = select.options[select.selectedIndex];

      if (!opt || !opt.value) {
        return { harga: 0, stok: null, nama: '' };
      }

      return {
        harga: Number(opt.getAttribute('data-harga')) || 0,
        stok: opt.getAttribute('data-stok') !== null ? Number(opt.getAttribute('data-stok')) : null,
        nama: opt.getAttribute('data-nama') || opt.textContent.trim()
      };
    }

    function hitung() {
      var data = ambilData();
      var jumlah = Math.max(0, parseInt(jumlahInput ? jumlahInput.value : '0', 10) || 0);

      tampilkan(hargaSatuanEl, formatNilai(hargaSatuanEl, data.harga));
      tampilkan(totalEl, formatRupiah(data.harga * jumlah));

      if (ringkasanTotal) {
        ringkasanTotal.textContent = formatRupiah(data.harga * jumlah);
      }

      if (ringkasanProduk && data.nama) {
        ringkasanProduk.textContent = data.nama;
      }

      if (ringkasanHarga) {
        ringkasanHarga.textContent = formatRupiah(data.harga);
      }

      if (ringkasanJumlah) {
        ringkasanJumlah.textContent = String(jumlah);
      }

      if (ringkasanStok) {
        ringkasanStok.textContent = data.stok === null ? '-' : String(Math.max(0, data.stok - jumlah));
      }

      if (jumlahInput) {
        if (data.stok !== null) {
          jumlahInput.max = String(Math.max(0, data.stok));
        } else {
          jumlahInput.removeAttribute('max');
        }
      }

      if (stokInfoEl) {
        stokInfoEl.classList.remove('is-warning', 'is-danger', 'is-success');

        if (data.stok === null) {
          stokInfoEl.textContent = 'Pilih produk untuk melihat ketersediaan stok.';
        } else {
          var sisa = data.stok - jumlah;

          if (data.stok <= 0) {
            stokInfoEl.textContent = 'Stok habis. Pilih produk lain.';
            stokInfoEl.classList.add('is-danger');
          } else if (jumlah > data.stok) {
            stokInfoEl.textContent = 'Jumlah melebihi stok. Tersedia ' + data.stok + ' porsi.';
            stokInfoEl.classList.add('is-danger');
          } else if (sisa <= 3) {
            stokInfoEl.textContent = 'Stok tersedia: ' + data.stok + ' porsi. Sisa setelah transaksi: ' + sisa + ' porsi.';
            stokInfoEl.classList.add('is-warning');
          } else {
            stokInfoEl.textContent = 'Stok tersedia: ' + data.stok + ' porsi. Sisa setelah transaksi: ' + sisa + ' porsi.';
            stokInfoEl.classList.add('is-success');
          }
        }
      }
    }

    select.addEventListener('change', hitung);

    if (jumlahInput) {
      jumlahInput.addEventListener('input', hitung);
      jumlahInput.addEventListener('change', hitung);
    }

    hitung();
  }

  /* -------------------------------------------------------
     6. Buka-tutup sidebar / menu mobile (opsional)
     ------------------------------------------------------- */
  function initReveal() {
    var items = document.querySelectorAll('[data-reveal]');

    if (!items.length || !('IntersectionObserver' in window)) {
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    Array.prototype.forEach.call(items, function (item) {
      observer.observe(item);
    });
  }

  /* -------------------------------------------------------
     Boot
     ------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    initIcons();
    initAlerts();
    initDeleteConfirm();
    initImagePreview();
    initHitungTotal();
    initReveal();
  });
})();
