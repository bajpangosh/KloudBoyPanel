<template>
  <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          Quick actions
        </p>
        <h3 class="mt-2 font-display text-2xl font-semibold text-slate-950">
          Live operational commands
        </h3>
      </div>
      <p class="max-w-xl text-sm leading-6 text-slate-500">
        These actions now run against the real backend instead of acting like display-only dashboard tags.
      </p>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
      <button
        v-for="action in actionButtons"
        :key="action.key"
        type="button"
        class="rounded-[24px] border px-4 py-4 text-left transition"
        :class="activeAction === action.key ? 'border-slate-900 bg-slate-900 text-white shadow-lg' : 'border-slate-200 bg-white/80 text-slate-700 hover:border-slate-900'"
        @click="activeAction = action.key"
      >
        <p class="font-display text-lg font-semibold">
          {{ action.label }}
        </p>
        <p
          class="mt-2 text-sm leading-6"
          :class="activeAction === action.key ? 'text-slate-200' : 'text-slate-500'"
        >
          {{ action.detail }}
        </p>
      </button>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
      <section class="rounded-[28px] border border-slate-200/80 bg-white/85 p-5">
        <div v-if="activeAction === 'create-site'">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Create Website
          </p>
          <div class="mt-4 grid gap-4">
            <label class="block">
              <span class="mb-2 block text-sm font-medium text-slate-700">Domain</span>
              <input
                v-model.trim="siteForm.domain"
                type="text"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                placeholder="example.com"
              />
            </label>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">PHP version</span>
                <select
                  v-model="siteForm.phpVersion"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                >
                  <option value="8.1">8.1</option>
                  <option value="8.2">8.2</option>
                  <option value="8.3">8.3</option>
                </select>
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Template</span>
                <select
                  v-model="siteForm.template"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                >
                  <option value="standard-wordpress">Standard WordPress</option>
                  <option value="woocommerce-optimized">WooCommerce optimized</option>
                  <option value="high-traffic-site">High-traffic site</option>
                </select>
              </label>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
              <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input v-model="siteForm.installWordpress" type="checkbox" />
                Install WordPress plan
              </label>
              <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input v-model="siteForm.enableRedis" type="checkbox" />
                Enable Redis cache plan
              </label>
            </div>

            <button
              type="button"
              class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white"
              :disabled="pendingAction === 'create-site'"
              @click="submitCreateSite"
            >
              {{ pendingAction === "create-site" ? "Creating..." : "Create Website" }}
            </button>
          </div>
        </div>

        <div v-else-if="activeAction === 'create-database'">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Create Database
          </p>
          <div class="mt-4 grid gap-4">
            <label class="block">
              <span class="mb-2 block text-sm font-medium text-slate-700">Database name</span>
              <input
                v-model.trim="databaseForm.name"
                type="text"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                placeholder="customer_portal"
              />
            </label>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Site domain (optional)</span>
                <select
                  v-model="databaseForm.siteDomain"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                >
                  <option value="">Standalone database</option>
                  <option
                    v-for="site in sites"
                    :key="site.id"
                    :value="site.domain"
                  >
                    {{ site.domain }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Username (optional)</span>
                <input
                  v-model.trim="databaseForm.username"
                  type="text"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
                  placeholder="customer_portal_usr"
                />
              </label>
            </div>

            <button
              type="button"
              class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white"
              :disabled="pendingAction === 'create-database'"
              @click="submitCreateDatabase"
            >
              {{ pendingAction === "create-database" ? "Creating..." : "Create Database" }}
            </button>
          </div>
        </div>

        <div v-else-if="activeAction === 'backup-now'">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Backup Now
          </p>
          <div class="mt-4 grid gap-4">
            <label class="block">
              <span class="mb-2 block text-sm font-medium text-slate-700">Site</span>
              <select
                v-model="backupForm.domain"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
              >
                <option value="">Select site</option>
                <option
                  v-for="site in sites"
                  :key="site.id"
                  :value="site.domain"
                >
                  {{ site.domain }}
                </option>
              </select>
            </label>

            <button
              type="button"
              class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white"
              :disabled="pendingAction === 'backup-now'"
              @click="submitBackup"
            >
              {{ pendingAction === "backup-now" ? "Running backup..." : "Backup Now" }}
            </button>
          </div>
        </div>

        <div v-else-if="activeAction === 'restart-services'">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Restart Services
          </p>
          <div class="mt-4 grid gap-3">
            <label
              v-for="service in restartableServicesWithStatus"
              :key="service.key"
              class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
            >
              <input
                v-model="restartForm.service"
                type="radio"
                name="service"
                :value="service.key"
              />
              <span>
                <strong class="block text-slate-950">{{ service.label }}</strong>
                <span class="mt-1 block leading-6 text-slate-500">{{ service.detail }}</span>
                <span class="mt-2 inline-flex">
                  <span class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">
                    {{ service.status }}
                  </span>
                </span>
              </span>
            </label>

            <button
              type="button"
              class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white"
              :disabled="pendingAction === 'restart-services'"
              @click="submitRestartService"
            >
              {{ pendingAction === "restart-services" ? "Restarting..." : "Restart Services" }}
            </button>
          </div>
        </div>

        <div v-else>
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Run Malware Scan
          </p>
          <div class="mt-4 grid gap-4">
            <label class="block">
              <span class="mb-2 block text-sm font-medium text-slate-700">Scope</span>
              <select
                v-model="scanForm.siteDomain"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
              >
                <option value="">All provisioned sites</option>
                <option
                  v-for="site in sites"
                  :key="site.id"
                  :value="site.domain"
                >
                  {{ site.domain }}
                </option>
              </select>
            </label>

            <button
              type="button"
              class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white"
              :disabled="pendingAction === 'malware-scan'"
              @click="submitMalwareScan"
            >
              {{ pendingAction === "malware-scan" ? "Scanning..." : "Run Malware Scan" }}
            </button>
          </div>
        </div>
      </section>

      <section class="rounded-[28px] border border-slate-200/80 bg-slate-950 p-5 text-white">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-teal-300">
          Result feed
        </p>

        <div
          v-if="actionResult"
          class="mt-4 space-y-4"
        >
          <div
            class="rounded-[24px] px-4 py-4"
            :class="resultToneClass"
          >
            <p class="font-display text-xl font-semibold">
              {{ actionResult.title }}
            </p>
            <p class="mt-2 text-sm leading-7">
              {{ actionResult.summary }}
            </p>
          </div>

          <pre class="overflow-auto rounded-[24px] bg-black/30 p-4 text-xs leading-6 text-slate-200">{{ formattedResult }}</pre>
        </div>

        <div
          v-else
          class="mt-4 rounded-[24px] border border-dashed border-white/20 bg-white/5 p-5 text-sm leading-7 text-slate-300"
        >
          Choose an action, submit it, and the live backend result will appear here.
        </div>
      </section>
    </div>
  </section>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, reactive, ref, watch } from "vue";

import type { ServiceStatus, SiteSummary } from "../data/panel";
import {
  createBackup,
  createDatabase,
  createSite,
  restartService,
  runMalwareScan
} from "../services/panelApi";

const props = defineProps<{
  sites: SiteSummary[];
  services: ServiceStatus[];
}>();

const emit = defineEmits<{
  (event: "refresh"): void;
}>();

type ActionKey = "create-site" | "create-database" | "backup-now" | "restart-services" | "malware-scan";

const actionButtons = [
  { key: "create-site", label: "Create Website", detail: "Provision a new hosted site scaffold." },
  { key: "create-database", label: "Create Database", detail: "Create a real SQLite database file and metadata record." },
  { key: "backup-now", label: "Backup Now", detail: "Archive a provisioned site immediately." },
  { key: "restart-services", label: "Restart Services", detail: "Attempt a real restart of supported host services." },
  { key: "malware-scan", label: "Run Malware Scan", detail: "Scan provisioned site files and write a report." }
] as const;

const restartableServices = [
  { key: "panel", label: "KloudBoy Panel", fallbackDetail: "Attempts a real panel restart on the current node." },
  { key: "openlitespeed", label: "OpenLiteSpeed", fallbackDetail: "Runs the host restart command for lsws or openlitespeed where available." },
  { key: "mariadb", label: "MariaDB", fallbackDetail: "Runs the host MariaDB restart command where available." },
  { key: "redis", label: "Redis", fallbackDetail: "Runs the host Redis restart command where available." }
] as const;

const activeAction = ref<ActionKey>("create-site");
const pendingAction = ref<ActionKey | "">("");
const actionResult = ref<{
  title: string;
  summary: string;
  tone: "success" | "warning" | "error";
  payload: unknown;
} | null>(null);

const siteForm = reactive({
  domain: "",
  phpVersion: "8.3",
  installWordpress: true,
  enableRedis: true,
  template: "standard-wordpress"
});

const databaseForm = reactive({
  name: "",
  siteDomain: "",
  username: ""
});

const backupForm = reactive({
  domain: ""
});

const restartForm = reactive({
  service: "panel"
});

const scanForm = reactive({
  siteDomain: ""
});

watch(
  () => props.sites,
  (sites) => {
    if (!backupForm.domain && sites.length > 0) {
      backupForm.domain = sites[0].domain;
    }
    if (!databaseForm.siteDomain && sites.length > 0) {
      databaseForm.siteDomain = sites[0].domain;
    }
  },
  { immediate: true }
);

const restartableServicesWithStatus = computed(() =>
  restartableServices.map((service) => {
    const liveService = props.services.find((candidate) => normalizeServiceKey(candidate.name) === service.key);

    return {
      key: service.key,
      label: service.label,
      detail: liveService?.detail || service.fallbackDetail,
      status: liveService?.status || "unknown"
    };
  })
);

const resultToneClass = computed(() => {
  switch (actionResult.value?.tone) {
    case "success":
      return "bg-emerald-500/15 text-emerald-100";
    case "warning":
      return "bg-amber-500/15 text-amber-100";
    case "error":
      return "bg-rose-500/15 text-rose-100";
    default:
      return "bg-white/5 text-slate-100";
  }
});

const formattedResult = computed(() => JSON.stringify(actionResult.value?.payload ?? {}, null, 2));

async function submitCreateSite(): Promise<void> {
  if (!siteForm.domain) {
    setError("Website creation requires a domain.");
    return;
  }

  await runAction("create-site", async () => {
    const payload = await createSite({ ...siteForm });
    emit("refresh");
    actionResult.value = {
      title: "Website created",
      summary: `Provisioned ${siteForm.domain} and refreshed the dashboard data.`,
      tone: "success",
      payload
    };
  });
}

async function submitCreateDatabase(): Promise<void> {
  if (!databaseForm.name && !databaseForm.siteDomain) {
    setError("Provide a database name or choose a site to derive one.");
    return;
  }

  await runAction("create-database", async () => {
    const payload = await createDatabase({ ...databaseForm });
    emit("refresh");
    actionResult.value = {
      title: "Database created",
      summary: "A real SQLite database file and metadata record were created.",
      tone: "success",
      payload
    };
  });
}

async function submitBackup(): Promise<void> {
  if (!backupForm.domain) {
    setError("Select a site before starting a backup.");
    return;
  }

  await runAction("backup-now", async () => {
    const payload = await createBackup({ ...backupForm });
    emit("refresh");
    actionResult.value = {
      title: "Backup completed",
      summary: `Created a backup archive for ${backupForm.domain}.`,
      tone: "success",
      payload
    };
  });
}

async function submitRestartService(): Promise<void> {
  await runAction("restart-services", async () => {
    const payload = await restartService({ ...restartForm }) as { status?: string; detail?: string };
    actionResult.value = {
      title: "Service restart finished",
      summary: payload.detail || "Restart attempt completed.",
      tone: payload.status === "completed" ? "success" : "warning",
      payload
    };
  });
}

async function submitMalwareScan(): Promise<void> {
  await runAction("malware-scan", async () => {
    const payload = await runMalwareScan({ ...scanForm }) as { findings?: Array<unknown>; reportPath?: string };
    emit("refresh");
    actionResult.value = {
      title: "Malware scan completed",
      summary:
        Array.isArray(payload.findings) && payload.findings.length > 0
          ? `Detected ${payload.findings.length} suspicious findings.`
          : "No suspicious findings were detected in the scanned files.",
      tone: Array.isArray(payload.findings) && payload.findings.length > 0 ? "warning" : "success",
      payload
    };
  });
}

async function runAction(key: ActionKey, callback: () => Promise<void>): Promise<void> {
  pendingAction.value = key;
  try {
    await callback();
  } catch (error) {
    if (axios.isAxiosError(error)) {
      setError(error.response?.data?.error || "The action failed.");
    } else {
      setError("The action failed.");
    }
  } finally {
    pendingAction.value = "";
  }
}

function setError(message: string): void {
  actionResult.value = {
    title: "Action failed",
    summary: message,
    tone: "error",
    payload: {
      error: message
    }
  };
}

function normalizeServiceKey(value: string): string {
  const normalized = value.trim().toLowerCase().replace(/[\s_-]+/g, "");

  switch (normalized) {
    case "kloudboypanel":
    case "panel":
      return "panel";
    case "openlitespeed":
    case "ols":
    case "lsws":
      return "openlitespeed";
    case "mariadb":
    case "mysql":
      return "mariadb";
    case "redis":
    case "redisserver":
      return "redis";
    default:
      return normalized;
  }
}
</script>
