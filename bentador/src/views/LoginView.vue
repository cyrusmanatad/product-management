<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import {
  SunIcon,
  MoonIcon,
  Squares2X2Icon,
  EnvelopeIcon,
  LockClosedIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const uiStore = useUiStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const auth = useAuthStore()

const handleLogin = async () => {
  isLoading.value = true

  const success = await auth.login(
    {
      email: email.value,
      password: password.value,
    },
    { redirect: false },
  )

  isLoading.value = false

  if (success) {
    if (auth.user?.must_reset_password) return
    const redirect = route.query.redirect
    router.push(
      typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
        ? redirect
        : '/vendors',
    )
  }
}
</script>

<template>
  <div
    class="bg-[#F9FAFB] dark:bg-dark-bg text-gray-800 dark:text-slate-200 min-h-screen flex flex-col justify-center items-center p-4 transition-colors duration-300"
  >
    <!-- Dark Mode Toggle (Floating Top Right) -->
    <div class="fixed top-6 right-6">
      <button
        @click="uiStore.toggleDarkMode"
        class="p-3 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-2xl shadow-xl hover:bg-gray-50 dark:hover:bg-slate-800 transition-all active:scale-90 group"
        type="button"
      >
        <component
          :is="uiStore.isDarkMode ? SunIcon : MoonIcon"
          class="w-5 h-5 text-teal-600 dark:text-teal-400 group-hover:rotate-12 transition-transform"
        />
      </button>
    </div>

    <div class="w-full max-w-md">
      <!-- Brand Logo -->
      <div class="flex flex-col items-center mb-10">
        <div
          class="w-16 h-16 bg-teal-500 rounded-2xl flex items-center justify-center text-white shadow-2xl shadow-teal-500/40 mb-4 animate-bounce-slow"
        >
          <Squares2X2Icon class="w-10 h-10" />
        </div>
        <h1 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">Benta Door</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 font-medium">
          Elevating your commerce experience
        </p>
      </div>

      <!-- Login Card -->
      <div
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border p-8 rounded-[2.5rem] shadow-2xl shadow-slate-200/50 dark:shadow-none relative overflow-hidden"
      >
        <!-- Decorative Gradient -->
        <div
          class="absolute top-0 right-0 w-32 h-32 bg-teal-500/5 dark:bg-teal-500/10 rounded-full -translate-y-16 translate-x-16"
        ></div>

        <div class="relative">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">Welcome Back</h2>
          <p
            class="text-xs text-gray-400 dark:text-slate-500 mb-8 uppercase tracking-widest font-black"
          >
            Enter your credentials
          </p>

          <form @submit.prevent="handleLogin" class="space-y-5">
            <!-- Email Field -->
            <div>
              <label
                class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-2 ml-1"
                >Email Address</label
              >
              <div class="relative group">
                <EnvelopeIcon
                  class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 group-focus-within:text-teal-500 transition-colors"
                />
                <input
                  v-model="email"
                  type="email"
                  required
                  autocomplete="username"
                  :aria-invalid="auth.loginError ? true : undefined"
                  :aria-describedby="auth.loginError ? 'login-error' : undefined"
                  class="w-full pl-12 pr-4 py-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-2xl text-sm font-bold focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 dark:text-white outline-none transition-all"
                  @input="auth.clearLoginError()"
                />
              </div>
            </div>

            <!-- Password Field -->
            <div>
              <div class="flex justify-between items-center mb-2 ml-1">
                <label
                  class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest"
                  >Password</label
                >
                <RouterLink
                  to="/reset-password"
                  class="text-[10px] font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest hover:underline"
                  >Forgot?</RouterLink
                >
              </div>
              <div class="relative group">
                <LockClosedIcon
                  class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 group-focus-within:text-teal-500 transition-colors"
                />
                <input
                  v-model="password"
                  type="password"
                  required
                  autocomplete="current-password"
                  :aria-invalid="auth.loginError ? true : undefined"
                  :aria-describedby="auth.loginError ? 'login-error' : undefined"
                  class="w-full pl-12 pr-4 py-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-2xl text-sm font-bold focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 dark:text-white outline-none transition-all"
                  @input="auth.clearLoginError()"
                />
              </div>
            </div>

            <!-- Remember Me -->
            <label class="flex items-center gap-3 cursor-pointer group w-fit ml-1">
              <input
                type="checkbox"
                class="w-5 h-5 rounded-lg border-gray-200 dark:border-dark-border text-teal-500 focus:ring-teal-500 transition-all cursor-pointer"
              />
              <span
                class="text-xs font-bold text-gray-500 dark:text-slate-400 group-hover:text-gray-700 dark:group-hover:text-slate-200 transition-colors"
                >Keep me signed in</span
              >
            </label>

            <p v-if="auth.loginError" id="login-error" role="alert" class="text-sm text-red-500">
              {{ auth.loginError }}
            </p>

            <!-- Sign In Button -->
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-4 bg-teal-500 hover:bg-teal-600 text-white font-black rounded-2xl shadow-xl shadow-teal-500/30 transition-all active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-3"
            >
              <div v-if="!isLoading" class="flex items-center gap-2">
                <span>Sign In to Portal</span>
                <ArrowRightIcon class="w-4 h-4" />
              </div>
              <div v-else class="flex items-center gap-2">
                <div
                  class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                ></div>
                <span>Authenticating...</span>
              </div>
            </button>
          </form>
        </div>
      </div>

      <!-- Footer Links -->
      <p class="text-center mt-8 text-sm text-gray-500 dark:text-slate-500">
        Don't have an account?
        <a href="#" class="font-black text-teal-600 dark:text-teal-400 hover:underline"
          >Contact Support</a
        >
      </p>
    </div>
  </div>
</template>

<style scoped>
@keyframes bounce-slow {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}
.animate-bounce-slow {
  animation: bounce-slow 3s infinite ease-in-out;
}
</style>
