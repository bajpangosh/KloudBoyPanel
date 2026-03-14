<template>
  <section
    v-if="page"
    class="space-y-6"
    :style="{ '--page-accent': page.accent }"
  >
    <div class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
      <div class="rounded-[28px] p-6 text-white feature-hero">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/70">
          {{ page.eyebrow }}
        </p>
        <h2 class="mt-3 font-display text-4xl font-semibold">
          {{ page.title }}
        </h2>
        <p class="mt-4 max-w-3xl text-base leading-7 text-white/85">
          {{ page.description }}
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <span class="rounded-full bg-white/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white">
            {{ page.phase }}
          </span>
          <span class="rounded-full bg-slate-950/35 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white">
            {{ page.endpoints.length }} integration surfaces
          </span>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
      <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          Operator actions
        </p>
        <div class="mt-4 grid gap-3">
          <article
            v-for="action in page.actions"
            :key="action"
            class="rounded-[24px] border border-slate-200/80 bg-white/85 p-4 text-sm font-medium text-slate-700"
          >
            {{ action }}
          </article>
        </div>
      </section>

      <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          Endpoints
        </p>
        <div class="mt-4 grid gap-3">
          <article
            v-for="endpoint in page.endpoints"
            :key="endpoint"
            class="rounded-[24px] border border-slate-200/80 bg-slate-950 p-4 font-mono text-sm text-teal-200"
          >
            {{ endpoint }}
          </article>
        </div>
      </section>

      <section class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          Implementation notes
        </p>
        <div class="mt-4 grid gap-4">
          <article
            v-for="group in page.sections"
            :key="group.title"
            class="rounded-[24px] border border-slate-200/80 bg-white/85 p-4"
          >
            <p class="font-display text-lg font-semibold text-slate-950">
              {{ group.title }}
            </p>
            <ul class="mt-3 grid gap-2 text-sm leading-6 text-slate-600">
              <li
                v-for="item in group.items"
                :key="item"
                class="rounded-2xl bg-slate-100/80 px-3 py-2"
              >
                {{ item }}
              </li>
            </ul>
          </article>
        </div>
      </section>
    </div>
  </section>

  <section
    v-else
    class="glass-panel accent-ring rounded-[32px] border border-white/70 p-6 text-slate-600"
  >
    Unknown page.
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";

import { getPanelPage } from "../data/panel";

const route = useRoute();

const page = computed(() => {
  const pageKey = String(route.meta.pageKey || "");
  return getPanelPage(pageKey);
});
</script>

<style scoped>
.feature-hero {
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--page-accent) 84%, black 16%), color-mix(in srgb, var(--page-accent) 52%, #020617 48%)),
    radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 30%);
}
</style>
