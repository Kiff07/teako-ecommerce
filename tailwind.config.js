/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./assets/**/*.{js,jsx}', './templates/**/*.twig'],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                cream: { 50: '#fdfaf4', 100: '#faf3e6', 200: '#f3e6cd', 300: '#e9d4ab' },
                cocoa: { 600: '#7a5a43', 700: '#5f4331', 800: '#4a3426', 900: '#37251b', 950: '#251711' },
                caramel: { 400: '#d99a3d', 500: '#c77f26', 600: '#a5631b' },
                terra: { 400: '#e07b54', 500: '#d05f3a', 600: '#b34a2a' },
                matcha: { 400: '#a3b86c', 500: '#8aa34e' },
                cream2: '#f7f0e3',
            },
            fontFamily: {
                display: ['"Fraunces"', 'Georgia', 'serif'],
                body: ['"Nunito Sans"', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
