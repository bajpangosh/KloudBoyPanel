<template>
  <article class="glass-panel accent-ring rounded-[28px] border border-white/70 p-5 shadow-glow">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          {{ metric.label }}
        </p>
        <p class="mt-3 font-display text-3xl font-semibold text-slate-900">
          {{ metric.value }}
        </p>
      </div>
      <span class="rounded-full bg-slate-900 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white">
        {{ metric.trend }}
      </span>
    </div>
  </article>
</template>

<script setup lang="ts">
defineProps<{
  metric: {
    key: string;
    label: string;
    value: string;
    trend: string;
  };
}>();
</script>

