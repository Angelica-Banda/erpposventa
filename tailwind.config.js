import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                purple: {
                    50: '#ecf5f1',
                    100: '#d9ebe2',
                    200: '#b5d7c6',
                    300: '#86b89d',
                    400: '#559779',
                    500: '#2d7658',
                    600: '#064e3b',
                    700: '#054332',
                    800: '#03372a',
                    900: '#022c22',
                    950: '#011e17',
                },
                indigo: {
                    50: '#ecf5f1',
                    100: '#d9ebe2',
                    200: '#b5d7c6',
                    300: '#86b89d',
                    400: '#559779',
                    500: '#2d7658',
                    600: '#064e3b',
                    700: '#054332',
                    800: '#03372a',
                    900: '#022c22',
                    950: '#011e17',
                },
            },
        },
    },

    plugins: [forms],
};
