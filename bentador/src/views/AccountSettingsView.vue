<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  LockClosedIcon,
  ShieldCheckIcon,
  SunIcon,
  MoonIcon,
  CheckCircleIcon,
  UserCircleIcon,
  BellIcon,
} from '@heroicons/vue/24/outline'
import AppHeader from '@/components/layouts/AppHeader.vue'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import type { ActionItem } from '@/types/header-types'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { PasswordFormErrors } from '@/types/auth'
import { useToastStore } from '@/stores/toast'
import axios from '@/utils/axios'

const auth = useAuthStore()
const uiStore = useUiStore()
const toastStore = useToastStore()
const router = useRouter()

const saving = ref(false)
const saved = ref(false)

const emailNotifications = ref(localStorage.getItem('emailNotifications') !== 'false')
const orderAlerts = ref(localStorage.getItem('orderAlerts') !== 'false')

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const passwordErrors = reactive<PasswordFormErrors>({
  current_password: [],
  password: [],
  password_confirmation: [],
})

const lastLogin = computed(() => {
  if (!auth.user?.last_login_at) return auth.user?.login ?? 'Not set'
  return new Date(auth.user.last_login_at).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
})

const actionItems: ActionItem[] = [
  {
    key: 'profile-settings',
    type: 'button',
    label: 'Profile Settings',
    variant: 'secondary',
    handler: () => router.push({ name: 'profile-settings' }),
  },
]

const clearPasswordErrors = () => {
  passwordErrors.current_password = []
  passwordErrors.password = []
  passwordErrors.password_confirmation = []
}

const resetPasswordForm = () => {
  passwordForm.current_password = ''
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
}

const handlePasswordSubmit = async () => {
  clearPasswordErrors()
  saving.value = true
  saved.value = false

  try {
    await auth.updatePassword({ ...passwordForm })
    saved.value = true
    resetPasswordForm()
    toastStore.addToast('Password updated successfully', 'success')
    setTimeout(() => {
      saved.value = false
    }, 3000)
  } catch (err: unknown) {
    if (axios.isAxiosError(err) && err.response?.status === 422) {
      const errors = err.response.data.errors as Record<string, string[]>
      Object.keys(errors).forEach((key) => {
        const messages = errors[key]
        if (key in passwordErrors && messages) {
          passwordErrors[key as keyof PasswordFormErrors] = messages
        }
      })
    } else {
      toastStore.addToast('Failed to update password', 'error')
    }
  } finally {
    saving.value = false
  }
}

const persistPreference = (key: string, value: boolean) => {
  localStorage.setItem(key, value.toString())
}

const onEmailNotificationsChange = () => {
  persistPreference('emailNotifications', emailNotifications.value)
}

const onOrderAlertsChange = () => {
  persistPreference('orderAlerts', orderAlerts.value)
}

onMounted(async () => {
  await auth.fetchUser(true)
})
</script>

<template>
  <WorkspaceLayout>
    <div class="max-w-3xl mx-auto">
      <AppHeader
        title="Account Settings"
        description="Security, preferences, and sign-in details"
        :breadcrumb="[{ label: 'Settings' }, { label: 'Account' }]"
        :action-items="actionItems"
        :show-menu-button="false"
      />

      <div class="space-y-6">
        <!-- Security info -->
        <section
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-6 shadow-sm"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600"
            >
              <ShieldCheckIcon class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-white">Security Overview</h3>
              <p class="text-[11px] text-gray-400">Your recent account activity</p>
            </div>
          </div>

          <dl class="grid sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-slate-800/40">
              <dt class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                Last Login
              </dt>
              <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-1">
                {{ lastLogin }}
              </dd>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-slate-800/40">
              <dt class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                IP Address
              </dt>
              <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-1">
                {{ auth.user?.last_login_ip ?? 'Not set' }}
              </dd>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-slate-800/40">
              <dt class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                Account Status
              </dt>
              <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-1">
                {{ auth.user?.status ?? 'Active' }}
              </dd>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-slate-800/40">
              <dt class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Role</dt>
              <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-1">
                {{ auth.user?.roles[0] ?? 'Not set' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Change password -->
        <section
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-6 sm:p-8 shadow-sm"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-10 h-10 rounded-xl bg-orange-50 dark:bg-orange-500/10 flex items-center justify-center text-orange-600"
            >
              <LockClosedIcon class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-white">Change Password</h3>
              <p class="text-[11px] text-gray-400">Use at least 8 characters</p>
            </div>
          </div>

          <form @submit.prevent="handlePasswordSubmit" class="space-y-4">
            <div>
              <label
                class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
              >
                Current Password
              </label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                autocomplete="current-password"
                class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
              />
              <p v-if="passwordErrors.current_password.length" class="mt-1 text-xs text-red-500">
                {{ passwordErrors.current_password[0] }}
              </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label
                  class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
                >
                  New Password
                </label>
                <input
                  v-model="passwordForm.password"
                  type="password"
                  autocomplete="new-password"
                  class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
                />
                <p v-if="passwordErrors.password.length" class="mt-1 text-xs text-red-500">
                  {{ passwordErrors.password[0] }}
                </p>
              </div>
              <div>
                <label
                  class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
                >
                  Confirm Password
                </label>
                <input
                  v-model="passwordForm.password_confirmation"
                  type="password"
                  autocomplete="new-password"
                  class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
                />
                <p
                  v-if="passwordErrors.password_confirmation.length"
                  class="mt-1 text-xs text-red-500"
                >
                  {{ passwordErrors.password_confirmation[0] }}
                </p>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
              <button
                type="submit"
                :disabled="saving"
                class="w-full sm:w-auto px-6 py-3 bg-teal-500 hover:bg-teal-600 disabled:opacity-60 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95"
              >
                {{ saving ? 'Updating...' : 'Update Password' }}
              </button>
              <p
                v-if="saved"
                class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400 font-medium"
              >
                <CheckCircleIcon class="w-4 h-4" /> Password updated
              </p>
            </div>
          </form>
        </section>

        <!-- Preferences -->
        <section
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-6 shadow-sm"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center text-purple-600"
            >
              <BellIcon class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-white">Preferences</h3>
              <p class="text-[11px] text-gray-400">Stored on this device</p>
            </div>
          </div>

          <div class="space-y-3">
            <fieldset class="p-4 rounded-2xl border border-gray-100 dark:border-dark-border">
              <legend class="text-sm font-semibold text-gray-900 dark:text-white">
                Color theme
              </legend>
              <p class="text-[11px] text-gray-400 mt-1">
                Green is the previous accent. Blue is the current palette.
              </p>
              <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Color theme">
                <label
                  class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-4 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-teal-700"
                  :class="
                    uiStore.colorTheme === 'green'
                      ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                      : 'border-gray-200 text-gray-600 dark:border-dark-border dark:text-slate-300'
                  "
                >
                  <input
                    type="radio"
                    name="color-theme"
                    value="green"
                    class="sr-only"
                    :checked="uiStore.colorTheme === 'green'"
                    @change="uiStore.setColorTheme('green')"
                  />
                  <span class="h-4 w-4 rounded-full" style="background: #0d9488"></span>
                  Green
                </label>
                <label
                  class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-4 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-teal-700"
                  :class="
                    uiStore.colorTheme === 'blue'
                      ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                      : 'border-gray-200 text-gray-600 dark:border-dark-border dark:text-slate-300'
                  "
                >
                  <input
                    type="radio"
                    name="color-theme"
                    value="blue"
                    class="sr-only"
                    :checked="uiStore.colorTheme === 'blue'"
                    @change="uiStore.setColorTheme('blue')"
                  />
                  <span class="h-4 w-4 rounded-full" style="background: #266ca9"></span>
                  Blue
                </label>
              </div>
            </fieldset>

            <label
              class="flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-dark-border cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/30 transition"
            >
              <div class="flex items-center gap-3">
                <component
                  :is="uiStore.isDarkMode ? MoonIcon : SunIcon"
                  class="w-5 h-5 text-gray-400"
                />
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">Dark Mode</p>
                  <p class="text-[11px] text-gray-400">Toggle application theme</p>
                </div>
              </div>
              <button
                type="button"
                role="switch"
                :aria-checked="uiStore.isDarkMode"
                @click="uiStore.toggleDarkMode()"
                class="w-11 h-6 rounded-full relative transition-colors"
                :class="uiStore.isDarkMode ? 'bg-teal-500' : 'bg-gray-200 dark:bg-slate-700'"
              >
                <span
                  class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                  :class="uiStore.isDarkMode ? 'left-0.5 translate-x-4' : 'left-0.5'"
                />
              </button>
            </label>

            <label
              class="flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-dark-border cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/30 transition"
            >
              <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                  Email Notifications
                </p>
                <p class="text-[11px] text-gray-400">Order and account updates</p>
              </div>
              <input
                v-model="emailNotifications"
                type="checkbox"
                class="w-5 h-5 rounded accent-teal-500"
                @change="onEmailNotificationsChange"
              />
            </label>

            <label
              class="flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-dark-border cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/30 transition"
            >
              <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Order Alerts</p>
                <p class="text-[11px] text-gray-400">New order notifications</p>
              </div>
              <input
                v-model="orderAlerts"
                type="checkbox"
                class="w-5 h-5 rounded accent-teal-500"
                @change="onOrderAlertsChange"
              />
            </label>
          </div>
        </section>

        <router-link
          :to="{ name: 'profile-settings' }"
          class="flex items-center gap-3 p-4 rounded-2xl border border-gray-100 dark:border-dark-border bg-white dark:bg-dark-card hover:border-teal-200 dark:hover:border-teal-500/30 transition group"
        >
          <div
            class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-slate-800 flex items-center justify-center text-gray-500 group-hover:text-teal-600"
          >
            <UserCircleIcon class="w-5 h-5" />
          </div>
          <div>
            <p class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-teal-600">
              Back to Profile Settings
            </p>
            <p class="text-[11px] text-gray-400">Edit your name and email</p>
          </div>
        </router-link>
      </div>
    </div>
  </WorkspaceLayout>
</template>
