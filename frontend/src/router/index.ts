import { createRouter, createWebHistory } from "vue-router";

import AppShell from "../layout/AppShell.vue";
import DashboardPage from "../pages/DashboardPage.vue";
import FeaturePage from "../pages/FeaturePage.vue";
import LoginPage from "../pages/LoginPage.vue";
import { panelPages } from "../data/panel";
import { authState, dashboardPath, initializeAuth, loginPath } from "../services/auth";

const routes = [
  {
    path: loginPath(),
    name: "login",
    component: LoginPage,
    meta: {
      public: true
    }
  },
  {
    path: "/",
    component: AppShell,
    meta: {
      requiresAuth: true
    },
    children: [
      {
        path: "",
        name: "dashboard",
        component: DashboardPage
      },
      ...panelPages.map((page) => ({
        path: page.path.replace(/^\//, ""),
        name: page.key,
        component: FeaturePage,
        meta: {
          pageKey: page.key,
          requiresAuth: true
        }
      }))
    ]
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

router.beforeEach(async (to) => {
  if (!authState.initialized) {
    await initializeAuth();
  }

  if (to.meta.public) {
    if (authState.admin) {
      return dashboardPath();
    }
    return true;
  }

  if (!authState.token) {
    return {
      path: loginPath(),
      query: {
        redirect: to.fullPath
      }
    };
  }

  if (!authState.admin) {
    return {
      path: loginPath(),
      query: {
        redirect: to.fullPath
      }
    };
  }

  return true;
});

export default router;

