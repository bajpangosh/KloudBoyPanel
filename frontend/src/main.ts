import { createApp } from "vue";

import App from "./App.vue";
import router from "./router";
import { initializeAuth } from "./services/auth";
import "./styles/main.css";

async function bootstrap(): Promise<void> {
  await initializeAuth();
  createApp(App).use(router).mount("#app");
}

void bootstrap();
