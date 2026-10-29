import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Mismos valores que public/css/main.css (web pública): grafito cálido del escudo, oro oliva del círculo, marfil.
 * Las escalas de color de Tailwind se sustituyen por tonos apagados de esa gama, así las clases que ya usan las
 * vistas (gray, emerald, amber, rose, blue, purple) salen en la paleta de Nortia sin tocar cada pantalla.
 */
const grafito = {
    50: '#FAFAF7', 100: '#F2F1EC', 150: '#E9E7DF', 200: '#DDD9CC', 300: '#C9C6BA', 400: '#6F7478',
    500: '#5A5F63', 600: '#4A4F54', 700: '#3A3F44', 800: '#2B2F33', 900: '#1F2225', 950: '#17191C',
};
const verde = {
    50: '#E4EFE8', 100: '#D2E5DA', 200: '#B9D6C5', 300: '#8DBCA2', 400: '#5E9C7E', 500: '#3F8264',
    600: '#2F6B4F', 700: '#285C44', 800: '#24543E', 900: '#1B4230', 950: '#0F2A1E',
};
const azul = {
    50: '#E3EBF1', 100: '#D1DDE7', 200: '#BCCDDA', 300: '#93AEC3', 400: '#6A8CA8', 500: '#4F7390',
    600: '#3D5A73', 700: '#35506A', 800: '#2E4A60', 900: '#243A4C', 950: '#16242F',
};
const ocre = {
    50: '#F6ECD6', 100: '#F0E0BA', 200: '#E3CF9E', 300: '#D2B46E', 400: '#BC9344', 500: '#A8782A',
    600: '#8A5A12', 700: '#6B4410', 800: '#5E3D08', 900: '#4A3006', 950: '#2E1D03',
};
const oro = {
    50: '#F8F4E4', 100: '#F1EBD3', 200: '#DCCF9B', 300: '#D5C27A', 400: '#B09A4B', 500: '#A28C3F',
    600: '#8A7634', 700: '#6B5A1A', 800: '#554814', 900: '#40360F', 950: '#26200A',
};
const rojo = {
    50: '#F5E3E1', 100: '#EFD1CE', 200: '#E2B8B4', 300: '#D08F89', 400: '#B96560', 500: '#A84441',
    600: '#9B2C2C', 700: '#872626', 800: '#6F2020', 900: '#571919', 950: '#360F0F',
};

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
                serif: ['"Source Serif 4"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                gray: grafito,
                emerald: verde,
                blue: azul,
                amber: ocre,
                purple: oro,
                rose: rojo,
                red: rojo,
                forest: { dark: '#A28C3F', medium: '#565551', light: '#D5C27A' },
                gold: { bright: '#A28C3F', warm: '#B09A4B', light: '#F1EBD3', text: '#6B5A1A', darkwarm: '#565551' },
            },
        },
    },

    plugins: [forms],
};
