<template>
  <span
    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
    :class="pillClass"
  >
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
  label: string;
}>();

const pillClass = computed(() => {
  switch (props.label.toLowerCase()) {
    case "online":
    case "completed":
    case "pass":
      return "bg-emerald-100 text-emerald-700";
    case "warning":
    case "warn":
    case "pending":
      return "bg-amber-100 text-amber-700";
    case "planned":
    case "skipped":
    case "inventory":
      return "bg-sky-100 text-sky-700";
    case "deleted":
    case "error":
      return "bg-rose-100 text-rose-700";
    default:
      return "bg-slate-200 text-slate-700";
  }
});
</script>

