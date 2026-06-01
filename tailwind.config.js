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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Barlow Condensed', 'sans-serif'],
            },
            colors: {
                'court-black': '#0A0A0C',
                'court-dark': '#121217',
                'hoop-orange': '#FF5D22',
                'data-slate': '#94A3B8',
            },
        },
    },

    plugins: [forms],
};
