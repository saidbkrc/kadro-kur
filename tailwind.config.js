import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Kozmetik sinif adlari PHP'de tanimli (magaza urunleri, oyuncu karti) —
        // taranmazsa Tailwind bu siniflari hic uretmez, urunler gorunmez kalir.
        './app/Support/CimShop.php',
        './app/Models/Player.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Barlow Condensed"', '"Arial Narrow"', 'Impact', ...defaultTheme.fontFamily.sans],
            },
            // Gece stadı: neredeyse siyah zemin + neon yelek yeşili vurgu (oyun menüsü tarzı)
            colors: {
                pitch: {
                    bg: '#0A0E1A',
                    surface: '#121829',
                    surface2: '#182037',
                    line: '#252F4A',
                    ink: '#F1F4FB',
                    muted: '#9AA4C0',
                    green: '#1F8F44',
                    green2: '#28AD55',
                },
                bibA: '#FF7A1A',      // turuncu yelek
                bibB: '#C8F04B',      // yeşil yelek
                gold: '#FFC83D',
            },
        },
    },

    plugins: [forms],
};
