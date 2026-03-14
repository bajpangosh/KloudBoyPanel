import { createRouter, createWebHistory } from "vue-router";

import DashboardPage from "../pages/DashboardPage.vue";
import FeaturePage from "../pages/FeaturePage.vue";
import { panelPages } from "../data/panel";

const routes = [
  {
    path: "/",
    name: "dashboard",
    component: DashboardPage
  },
  ...panelPages.map((page) => ({
    path: page.path,
    name: page.key,
    component: FeaturePage,
    meta: {
      pageKey: page.key
    }
  }))
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

export default router;

