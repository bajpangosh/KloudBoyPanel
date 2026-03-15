<template>
  <section class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-8 lg:px-6">
    <div class="grid w-full gap-6 lg:grid-cols-[1.05fr_0.95fr]">
      <article class="glass-panel accent-ring rounded-[36px] border border-white/70 p-7 lg:p-10">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
          KloudBoy access
        </p>
        <h1 class="mt-3 font-display text-4xl font-semibold text-slate-950 lg:text-5xl">
          Sign in to the hidden control path.
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
          Your install now generates an initial panel admin on first boot. Use the credentials from the startup logs or the generated credentials file, then change them before exposing the panel anywhere public.
        </p>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
          <article class="rounded-[28px] border border-slate-200/80 bg-white/85 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              Login URL
            </p>
            <p class="mt-3 break-all font-display text-xl font-semibold text-slate-950">
              {{ visibleLoginUrl }}
            </p>
          </article>

          <article class="rounded-[28px] border border-slate-200/80 bg-slate-950 p-5 text-white">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-teal-300">
              First-run tip
            </p>
            <p class="mt-3 text-sm leading-7 text-slate-300">
              In Docker, run `docker logs kloudboy-panel | rg "kloudboy"` to see the generated bootstrap admin details.
            </p>
          </article>
        </div>
      </article>

      <article class="glass-panel accent-ring rounded-[36px] border border-white/70 p-7 lg:p-10">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
          Panel login
        </p>
        <form class="mt-6 space-y-4" @submit.prevent="submitLogin">
          <label class="block">
            <span class="mb-2 block text-sm font-medium text-slate-700">Email</span>
            <input
              v-model.trim="email"
              type="email"
              autocomplete="username"
              required
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
              placeholder="admin@kloudboy.local"
            />
          </label>

          <label class="block">
            <span class="mb-2 block text-sm font-medium text-slate-700">Password</span>
            <input
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-slate-950"
              placeholder="Generated during first boot"
            />
          </label>

          <p
            v-if="errorMessage"
            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
          >
            {{ errorMessage }}
          </p>

          <button
            type="submit"
            class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-slate-800"
            :disabled="submitting"
          >
            {{ submitting ? "Signing in..." : "Sign in" }}
          </button>
        </form>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

import { dashboardPath, displayLoginUrl, login } from "../services/auth";

const router = useRouter();
const route = useRoute();

const email = ref("");
const password = ref("");
const submitting = ref(false);
const errorMessage = ref("");

const visibleLoginUrl = computed(() => displayLoginUrl());

async function submitLogin(): Promise<void> {
  errorMessage.value = "";
  submitting.value = true;

  try {
    await login({
      email: email.value,
      password: password.value
    });

    const redirect = typeof route.query.redirect === "string" ? route.query.redirect : dashboardPath();
    await router.push(redirect);
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.error || "Login failed.";
    } else {
      errorMessage.value = "Login failed.";
    }
  } finally {
    submitting.value = false;
  }
}
</script>
