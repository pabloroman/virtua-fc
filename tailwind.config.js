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
                sans: ['Barlow Semi Condensed', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                xs: '0.8rem',
                sm: '1rem',
                base: '1.25rem',
                'xl': '1.563rem',
                '2xl': '1.953rem',
                '3xl': '2.441rem',
                '4xl': '3.052rem',
            }
        },
    },

    plugins: [forms],
};
