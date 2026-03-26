import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbite from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        // Flowbite JS components
        './node_modules/flowbite/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Gender colours — mirrors config/entry_types.php
                // Edit there, then update here to keep in sync
                gender: {
                    male:    '#60A5FA',
                    female:  '#F472B6',
                    unknown: '#92400E',
                },
            },
        },
    },

    plugins: [
        forms,
        flowbite,
    ],
};
