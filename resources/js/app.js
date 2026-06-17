import './bootstrap';
import Swal from 'sweetalert2';

window.Swal = Swal;

/**
 * Onay gerektiren butonlar: wire:click ile birlikte
 *   data-confirm="mesaj"  (zorunlu)
 *   data-confirm-danger="false"  (opsiyonel — yeşil/soru ikonu)
 * taşır. HTML attribute'una hiç JS yazılmaz; onay buradan yönetilir.
 */
document.addEventListener(
    'click',
    function (event) {
        const el = event.target.closest('[data-confirm]');
        if (!el) {
            return;
        }

        // Onaylanmış ikinci tıklama: geç (wire:click çalışsın)
        if (el.dataset.confirmed === '1') {
            delete el.dataset.confirmed;
            return;
        }

        // İlk tıklama: durdur, onay sor (Livewire'dan önce — capture fazı)
        event.preventDefault();
        event.stopImmediatePropagation();

        const danger = el.dataset.confirmDanger !== 'false';

        Swal.fire({
            text: el.dataset.confirm,
            icon: danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'Vazgeç',
            reverseButtons: true,
            focusCancel: true,
            background: '#11231A',
            color: '#EDF7EF',
            confirmButtonColor: danger ? '#b91c1c' : '#2C7A48',
            cancelButtonColor: '#23402F',
        }).then((result) => {
            if (result.isConfirmed) {
                el.dataset.confirmed = '1';
                el.click(); // bu sefer dinleyici izin verir → wire:click tetiklenir
            }
        });
    },
    true // capture
);
