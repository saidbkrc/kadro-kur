import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Barlow Condensed"', '"Arial Narrow"', 'Impact', ...defaultTheme.fontFamily.sans],
            },
            // Lacivert zemin + logodaki canlı saha yeşili aksan (kadro-logo)
            colors: {
                pitch: {
                    bg: '#0F1F41',
                    surface: '#16294E',
                    surface2: '#1E3560',
                    line: '#2C4577',
                    ink: '#EDF2FB',
                    muted: '#92A5CC',
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
