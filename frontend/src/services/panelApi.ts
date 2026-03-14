import axios from "axios";

import type { DashboardOverview } from "../data/panel";
import { fallbackOverview } from "../data/panel";

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || "http://localhost:8443"
});

export async function fetchDashboardOverview(): Promise<DashboardOverview> {
  try {
    const response = await api.get<DashboardOverview>("/api/dashboard/overview");
    return response.data;
  } catch (error) {
    console.warn("Falling back to local dashboard blueprint", error);
    return fallbackOverview;
  }
}

