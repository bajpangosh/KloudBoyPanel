import type { DashboardOverview, SiteSummary } from "../data/panel";
import { api } from "./http";

export interface CreateSitePayload {
  domain: string;
  phpVersion: string;
  installWordpress: boolean;
  enableRedis: boolean;
  template: string;
}

export interface CreateDatabasePayload {
  name: string;
  siteDomain: string;
  username: string;
}

export interface BackupPayload {
  domain: string;
}

export interface RestartServicePayload {
  service: string;
}

export interface MalwareScanPayload {
  siteDomain: string;
}

export async function fetchDashboardOverview(): Promise<DashboardOverview> {
  const response = await api.get<DashboardOverview>("/api/dashboard/overview");
  return response.data;
}

export async function fetchSites(): Promise<SiteSummary[]> {
  const response = await api.get<SiteSummary[]>("/api/sites");
  return response.data;
}

export async function createSite(payload: CreateSitePayload): Promise<unknown> {
  const response = await api.post("/api/sites/create", payload);
  return response.data;
}

export async function createDatabase(payload: CreateDatabasePayload): Promise<unknown> {
  const response = await api.post("/api/databases/create", payload);
  return response.data;
}

export async function createBackup(payload: BackupPayload): Promise<unknown> {
  const response = await api.post("/api/backup", payload);
  return response.data;
}

export async function restartService(payload: RestartServicePayload): Promise<unknown> {
  const response = await api.post("/api/server/services/restart", payload);
  return response.data;
}

export async function runMalwareScan(payload: MalwareScanPayload): Promise<unknown> {
  const response = await api.post("/api/security/malware-scan", payload);
  return response.data;
}
