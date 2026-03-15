import axios from "axios";

const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL;
const storageKey = "kloudboy.auth_token";

export const api = axios.create({
  baseURL: configuredBaseUrl === undefined ? "" : configuredBaseUrl
});

api.interceptors.request.use((request) => {
  const token = window.localStorage.getItem(storageKey);
  if (token) {
    request.headers = request.headers || {};
    request.headers.Authorization = `Bearer ${token}`;
  }
  return request;
});

export function getStoredToken(): string {
  return window.localStorage.getItem(storageKey) || "";
}

export function setStoredToken(token: string): void {
  window.localStorage.setItem(storageKey, token);
}

export function clearStoredToken(): void {
  window.localStorage.removeItem(storageKey);
}

