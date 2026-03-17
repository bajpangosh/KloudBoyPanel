<template>
  <section class="space-y-6">
    <div class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
            Dashboard
          </p>
          <h2 class="mt-3 font-display text-4xl font-semibold text-slate-950">
            Faster hosting operations, without the panel bloat.
          </h2>
          <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
            This cockpit is wired around the KloudBoy spec: site provisioning, database management, backups, server monitoring, and WordPress tooling for WooCommerce-heavy workloads.
          </p>
          <p class="mt-5 inline-flex rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700">
            Authenticated operator mode with live backend state
          </p>
        </div>

        <div class="rounded-[28px] bg-slate-950 p-5 text-white">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-orange-300">
            Bootstrap notes
          </p>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            {{ dashboardMessage }}
          </p>
          <p class="mt-4 text-xs uppercase tracking-[0.18em] text-slate-400">
            {{ loading ? "Refreshing..." : "Last refresh ready" }}
          </p>
        </div>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <MetricCard
        v-for="metric in overview.metrics"
        :key="metric.key"
        :metric="metric"
      />
    </div>

    <section
      v-if="loadError"
      class="rounded-[28px] border border-rose-200 bg-rose-50 px-5 py-4 text-sm leading-7 text-rose-700"
    >
      {{ loadError }}
    </section>

    <QuickActionsPanel
      :sites="sites"
      :services="overview.services"
      @refresh="refreshDashboard"
    />

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
      <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              Recent sites
            </p>
            <h3 class="mt-2 font-display text-2xl font-semibold text-slate-950">
              Provisioned workloads
            </h3>
          </div>
          <RouterLink
            to="/websites"
            class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white"
          >
            Open websites
          </RouterLink>
        </div>

        <div class="mt-5 grid gap-4">
          <article
            v-for="site in overview.recentSites"
            :key="site.id"
            class="rounded-[24px] border border-slate-200/80 bg-white/85 p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-display text-xl font-semibold text-slate-950">
                  {{ site.domain }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ site.systemUser }} · PHP {{ site.phpVersion }}
                </p>
              </div>
              <div class="flex gap-2">
                <StatusPill :label="site.status" />
                <StatusPill :label="site.redisEnabled ? 'online' : 'planned'" />
              </div>
            </div>
          </article>

          <article
            v-if="overview.recentSites.length === 0"
            class="rounded-[24px] border border-dashed border-slate-300 bg-white/60 p-5 text-sm leading-7 text-slate-600"
          >
            No sites have been provisioned yet. The backend is ready to record site scaffolds through `POST /api/sites/create`.
          </article>
        </div>
      </section>

      <div class="space-y-6">
        <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Service health
          </p>
          <div class="mt-4 grid gap-3">
            <article
              v-for="service in overview.services"
              :key="service.name"
              class="rounded-[24px] border border-slate-200/80 bg-white/85 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-display text-lg font-semibold text-slate-950">
                    {{ service.name }}
                  </p>
                  <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ service.detail }}
                  </p>
                </div>
                <StatusPill :label="service.status" />
              </div>
            </article>
          </div>
        </section>

        <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Alerts
          </p>
          <div class="mt-4 grid gap-3">
            <article
              v-for="alert in overview.alerts"
              :key="`${alert.title}-${alert.created}`"
              class="rounded-[24px] border border-slate-200/80 bg-white/85 p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-display text-lg font-semibold text-slate-950">
                    {{ alert.title }}
                  </p>
                  <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ alert.detail }}
                  </p>
                </div>
                <StatusPill :label="alert.level" />
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import axios from "axios";

import MetricCard from "../components/MetricCard.vue";
import QuickActionsPanel from "../components/QuickActionsPanel.vue";
import StatusPill from "../components/StatusPill.vue";
import type { DashboardOverview, SiteSummary } from "../data/panel";
import { fetchDashboardOverview, fetchSites } from "../services/panelApi";

const loading = ref(true);
const loadError = ref("");
const overview = ref<DashboardOverview>({
  metrics: [],
  quickActions: [],
  services: [],
  recentSites: [],
  recentBackups: [],
  alerts: []
});
const sites = ref<SiteSummary[]>([]);

const dashboardMessage = computed(() =>
  overview.value.recentSites.length > 0
      ? "Live site, backup, and service data is flowing from the API. Use the quick actions below to operate the node directly."
      : "The panel is online with live API state. Use the quick actions below to provision the first site, database, backup, or malware scan."
);

async function refreshDashboard(): Promise<void> {
  loading.value = true;
  loadError.value = "";

  try {
    const [dashboardOverview, siteList] = await Promise.all([
      fetchDashboardOverview(),
      fetchSites()
    ]);

    overview.value = dashboardOverview;
    sites.value = siteList;
  } catch (error) {
    if (axios.isAxiosError(error)) {
      loadError.value = error.response?.data?.error || "Failed to load live dashboard state.";
    } else {
      loadError.value = "Failed to load live dashboard state.";
    }
  } finally {
    loading.value = false;
  }
}

onMounted(refreshDashboard);
</script>
