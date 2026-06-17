import './bootstrap';
import Swal from 'sweetalert2';

window.Swal = Swal;

/**
 * Siteyle uyumlu (koyu yeşil tema) onay penceresi.
 * Kullanım: kkConfirm('mesaj', { danger: true }).then(ok => ok && $wire.method())
 */
window.kkConfirm = function (message, { danger = true } = {}) {
    return Swal.fire({
        text: message,
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
    }).then((result) => result.isConfirmed);
};
