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
            // kadro.html'in koyu yeşil saha kimliği
            colors: {
                pitch: {
                    bg: '#0A150E',
                    surface: '#11231A',
                    surface2: '#16301F',
                    line: '#23402F',
                    ink: '#EDF7EF',
                    muted: '#8AAD94',
                    green: '#15502F',
                    green2: '#196038',
                },
                bibA: '#FF7A1A',      // turuncu yelek
                bibB: '#C8F04B',      // yeşil yelek
                gold: '#FFC83D',
            },
        },
    },

    plugins: [forms],
};
