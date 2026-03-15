import type { DashboardOverview } from "../data/panel";
import { fallbackOverview } from "../data/panel";
import { api } from "./http";

export async function fetchDashboardOverview(): Promise<DashboardOverview> {
  try {
    const response = await api.get<DashboardOverview>("/api/dashboard/overview");
    return response.data;
  } catch (error) {
    console.warn("Falling back to local dashboard blueprint", error);
    return fallbackOverview;
  }
}
