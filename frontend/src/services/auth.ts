import { reactive } from "vue";

import { api, clearStoredToken, getStoredToken, setStoredToken } from "./http";

export interface Admin {
  id: number;
  email: string;
  role: string;
  createdAt: string;
  updatedAt: string;
}

interface LoginResponse {
  token: string;
  expiresAt: string;
  admin: Admin;
}

const panelBasePath = normalizePanelBase(import.meta.env.BASE_URL || "/");

export const authState = reactive({
  token: getStoredToken(),
  admin: null as Admin | null,
  initialized: false
});

let initializationPromise: Promise<void> | null = null;

export function loginPath(): string {
  return "/login";
}

export function dashboardPath(): string {
  return "/";
}

export function displayLoginUrl(): string {
  const origin = window.location.origin;
  return `${origin}${panelBasePath}login`;
}

export function isAuthenticated(): boolean {
  return Boolean(authState.token && authState.admin);
}

export async function initializeAuth(): Promise<void> {
  if (authState.initialized) {
    return;
  }
  if (!authState.token) {
    authState.initialized = true;
    return;
  }
  if (!initializationPromise) {
    initializationPromise = (async () => {
      try {
        await loadCurrentAdmin();
      } catch {
        clearSession();
      } finally {
        authState.initialized = true;
        initializationPromise = null;
      }
    })();
  }
  await initializationPromise;
}

export async function login(credentials: {
  email: string;
  password: string;
}): Promise<void> {
  const response = await api.post<LoginResponse>("/api/auth/login", credentials);
  authState.token = response.data.token;
  authState.admin = response.data.admin;
  setStoredToken(response.data.token);
  authState.initialized = true;
}

export async function loadCurrentAdmin(): Promise<Admin> {
  const response = await api.get<{ admin: Admin }>("/api/auth/me");
  authState.admin = response.data.admin;
  return response.data.admin;
}

export async function logout(): Promise<void> {
  try {
    await api.post("/api/auth/logout");
  } finally {
    clearSession();
  }
}

export function clearSession(): void {
  authState.token = "";
  authState.admin = null;
  clearStoredToken();
}

function normalizePanelBase(base: string): string {
  if (!base || base === "/") {
    return "/";
  }
  return base.endsWith("/") ? base : `${base}/`;
}
