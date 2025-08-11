/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/admin/**/*.{js,vue,ts}',
    './assets/catalog/**/*.{js,vue,ts}',
    // './assets/shared/**/*.{js,vue,ts}', // shared более не используется
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}

