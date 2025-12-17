/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./assets/**/*.js",
    "./assets/**/*.vue",
    "./public/**/*.html",
  ],
  safelist: [
    {
      pattern: /^bg-(red|green|amber|blue|purple|yellow|gray)-(400|500|600|700|800|900)/,
    },
    {
      pattern: /^text-(red|green|amber|blue|purple|yellow|gray)-(200|300|400|500)/,
    },
  ],
  theme: {
    extend: {
      colors: {
        gray: {
          750: '#2d3748',
        },
      },
    },
  },
  plugins: [],
}
