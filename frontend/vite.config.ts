import { loadEnv, defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");

  return {
    plugins: [vue()],
    base: env.VITE_PANEL_BASE_PATH || "/",
    server: {
      port: 5173
    }
  };
});

