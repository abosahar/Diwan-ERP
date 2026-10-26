import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const brandScale = {
    50: '#f0f7f6',
    100: '#d7ece8',
    200: '#b0d9d2',
    300: '#7fc0b7',
    400: '#3fa398',
    500: '#0d9488',
    600: '#0f766e',
    700: '#115e59',
    800: '#134e4a',
    900: '#0a3331',
    950: '#052321',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', 'IBM Plex Sans Arabic', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // The system's identity colour (teal/emerald). `indigo` is remapped to the same scale,
                // so every page that uses indigo-* takes the new palette without being edited.
                brand: brandScale,
                indigo: brandScale,
                navy: { 800: '#0f172a', 850: '#0b1120', 900: '#020617' },
                gold: { 500: '#d97706', 600: '#b45309' },
            },
        },
    },

    plugins: [forms],
};

