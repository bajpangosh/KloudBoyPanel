/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./index.html", "./src/**/*.{vue,ts}"],
  theme: {
    extend: {
      boxShadow: {
        glow: "0 24px 80px rgba(15, 118, 110, 0.18)"
      },
      backgroundImage: {
        mesh: "radial-gradient(circle at top left, rgba(20, 184, 166, 0.18), transparent 35%), radial-gradient(circle at top right, rgba(249, 115, 22, 0.18), transparent 30%), linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(241, 245, 249, 0.92))"
      }
    }
  },
  plugins: []
};

