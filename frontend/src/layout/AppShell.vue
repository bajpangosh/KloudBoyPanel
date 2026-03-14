<template>
  <div class="min-h-screen bg-mesh text-slate-900">
    <div class="mx-auto grid min-h-screen max-w-7xl gap-6 px-4 py-5 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-6">
      <aside class="glass-panel accent-ring rounded-[32px] border border-white/70 p-5 lg:sticky lg:top-5 lg:h-[calc(100vh-2.5rem)]">
        <div class="rounded-[28px] bg-slate-950 p-5 text-white">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-teal-300">
            KloudBoy
          </p>
          <h1 class="mt-3 font-display text-3xl font-semibold">
            Hosting control for fast WordPress fleets.
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-300">
            OpenLiteSpeed-focused orchestration with isolated sites, backup workflows, and a sharper operator cockpit.
          </p>
        </div>

        <nav class="mt-5 grid gap-2 md:grid-cols-2 lg:grid-cols-1">
          <RouterLink
            v-for="item in navigationItems"
            :key="item.path"
            :to="item.path"
            class="rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-white/70"
            :class="route.path === item.path ? 'bg-white text-slate-950 shadow-lg' : 'text-slate-600'"
          >
            {{ item.label }}
          </RouterLink>
        </nav>

        <div class="mt-5 rounded-[28px] border border-slate-200/80 bg-white/80 p-4">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Panel access
          </p>
          <p class="mt-3 font-display text-lg font-semibold text-slate-950">
            {{ panelAccess }}
          </p>
          <p class="mt-2 text-sm leading-6 text-slate-600">
            Domain, port, and hidden login path are configurable from the backend environment.
          </p>
        </div>
      </aside>

      <main class="pb-6">
        <header class="glass-panel accent-ring mb-6 rounded-[32px] border border-white/70 px-6 py-5">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                Panel blueprint
              </p>
              <p class="mt-2 font-display text-3xl font-semibold text-slate-950">
                OpenLiteSpeed-centered control plane
              </p>
            </div>
            <div class="flex flex-wrap gap-3">
              <div class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-white">
                Ubuntu 24
              </div>
              <div class="rounded-full bg-teal-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-teal-700">
                Vue 3 + Go + SQLite
              </div>
            </div>
          </div>
        </header>

        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { RouterLink, RouterView, useRoute } from "vue-router";

import { navigationItems } from "../data/panel";

const route = useRoute();

const panelAccess = computed(() => "panel.example.com:8443/kb-admin-demo/");
</script>

