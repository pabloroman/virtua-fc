import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                heading: ['Barlow Condensed', ...defaultTheme.fontFamily.sans],
                body: ['Inter', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                surface: {
                    900: '#0B1120',
                    800: '#0F172A',
                    700: '#1E293B',
                    600: '#334155',
                },
                accent: {
                    blue: '#3B82F6',
                    gold: '#F59E0B',
                    green: '#22C55E',
                    red: '#EF4444',
                    orange: '#F97316',
                },
                pitch: {
                    dark: '#1a5c2a',
                    base: '#1e6b31',
                    light: '#22783a',
                    line: 'rgba(255,255,255,0.25)',
                },
            },
        },
    },

    plugins: [forms, typography],
};
