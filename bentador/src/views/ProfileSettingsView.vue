<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { HomeIcon, UserCircleIcon, EnvelopeIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import AppHeader from '@/components/layouts/AppHeader.vue'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import type { ActionItem } from '@/types/header-types'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { ProfileFormErrors } from '@/types/auth'
import { useToastStore } from '@/stores/toast'
import axios from '@/utils/axios'

const auth = useAuthStore()
const uiStore = useUiStore()
const toastStore = useToastStore()
const router = useRouter()

const saving = ref(false)
const saved = ref(false)

const form = reactive({
  name: '',
  email: '',
})

const formErrors = reactive<ProfileFormErrors>({
  name: [],
  email: [],
})

const avatarUrl = computed(() => {
  const name = encodeURIComponent(form.name || auth.user?.name || 'User')
  return `https://ui-avatars.com/api/?name=${name}&background=${uiStore.avatarBackground}&color=fff&size=128`
})

const roleLabel = computed(() => auth.user?.roles[0] ?? 'User')
const memberSince = computed(() => {
  if (!auth.user?.created_at) return 'Not set'
  return new Date(auth.user.created_at).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
})

const actionItems: ActionItem[] = [
  {
    key: 'account-settings',
    type: 'button',
    label: 'Account Settings',
    variant: 'secondary',
    handler: () => router.push({ name: 'account-settings' }),
  },
]

const clearErrors = () => {
  formErrors.name = []
  formErrors.email = []
}

const syncForm = () => {
  if (!auth.user) return
  form.name = auth.user.name
  form.email = auth.user.email
}

const handleSubmit = async () => {
  clearErrors()
  saving.value = true
  saved.value = false

  try {
    await auth.updateProfile({ name: form.name, email: form.email })
    saved.value = true
    toastStore.addToast('Profile updated successfully', 'success')
    setTimeout(() => {
      saved.value = false
    }, 3000)
  } catch (err: unknown) {
    if (axios.isAxiosError(err) && err.response?.status === 422) {
      const errors = err.response.data.errors as Record<string, string[]>
      Object.keys(errors).forEach((key) => {
        const messages = errors[key]
        if (key in formErrors && messages) {
          formErrors[key as keyof ProfileFormErrors] = messages
        }
      })
    } else {
      toastStore.addToast('Failed to update profile', 'error')
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await auth.fetchUser(true)
  syncForm()
})
</script>

<template>
  <WorkspaceLayout>
    <div class="max-w-3xl mx-auto">
      <AppHeader
        title="Profile Settings"
        description="Manage your personal information"
        :breadcrumb="[{ label: 'Settings' }, { label: 'Profile' }]"
        :action-items="actionItems"
        :show-menu-button="false"
      />

      <div class="space-y-6">
        <section
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-6 sm:p-8 shadow-sm"
        >
          <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8">
            <img
              :src="avatarUrl"
              alt="Profile avatar"
              class="w-24 h-24 rounded-2xl border-4 border-white dark:border-slate-700 shadow-lg"
            />
            <div class="text-center sm:text-left">
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ auth.user?.name }}</h2>
              <p class="text-sm text-teal-600 dark:text-teal-400 font-medium mt-1">
                {{ roleLabel }}
              </p>
              <p class="text-xs text-gray-400 mt-2">Member since {{ memberSince }}</p>
              <span
                class="inline-flex mt-3 px-3 py-1 rounded-full text-[11px] font-bold"
                :class="
                  auth.user?.is_active !== false
                    ? 'bg-green-50 text-green-600 dark:bg-green-500/10'
                    : 'bg-red-50 text-red-600 dark:bg-red-500/10'
                "
              >
                {{ auth.user?.status ?? 'Active' }}
              </span>
            </div>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-5">
            <div>
              <label
                class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
              >
                Full Name
              </label>
              <div class="relative">
                <UserCircleIcon class="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                <input
                  v-model="form.name"
                  type="text"
                  class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
                  placeholder="Your full name"
                />
              </div>
              <p v-if="formErrors.name.length" class="mt-1 text-xs text-red-500">
                {{ formErrors.name[0] }}
              </p>
            </div>

            <div>
              <label
                class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
              >
                Email Address
              </label>
              <div class="relative">
                <EnvelopeIcon class="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                <input
                  v-model="form.email"
                  type="email"
                  class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
                  placeholder="you@example.com"
                />
              </div>
              <p v-if="formErrors.email.length" class="mt-1 text-xs text-red-500">
                {{ formErrors.email[0] }}
              </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
              <button
                type="submit"
                :disabled="saving"
                class="w-full sm:w-auto px-6 py-3 bg-teal-500 hover:bg-teal-600 disabled:opacity-60 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95"
              >
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
              <p
                v-if="saved"
                class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400 font-medium"
              >
                <CheckCircleIcon class="w-4 h-4" /> Saved successfully
              </p>
            </div>
          </form>
        </section>

        <section
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-6 shadow-sm"
        >
          <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Quick Links</h3>
          <div class="grid sm:grid-cols-2 gap-3">
            <router-link
              :to="{ name: 'account-settings' }"
              class="flex items-center gap-3 p-4 rounded-2xl border border-gray-100 dark:border-dark-border hover:border-teal-200 dark:hover:border-teal-500/30 hover:bg-teal-50/50 dark:hover:bg-teal-500/5 transition group"
            >
              <div
                class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600"
              >
                <HomeIcon class="w-5 h-5" />
              </div>
              <div>
                <p
                  class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-teal-600"
                >
                  Account Settings
                </p>
                <p class="text-[11px] text-gray-400">Password & security</p>
              </div>
            </router-link>
            <router-link
              :to="{ name: 'home' }"
              class="flex items-center gap-3 p-4 rounded-2xl border border-gray-100 dark:border-dark-border hover:border-teal-200 dark:hover:border-teal-500/30 hover:bg-teal-50/50 dark:hover:bg-teal-500/5 transition group"
            >
              <div
                class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-slate-800 flex items-center justify-center text-gray-500"
              >
                <UserCircleIcon class="w-5 h-5" />
              </div>
              <div>
                <p
                  class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-teal-600"
                >
                  Browse stores
                </p>
                <p class="text-[11px] text-gray-400">Discover products and stores</p>
              </div>
            </router-link>
          </div>
        </section>
      </div>
    </div>
  </WorkspaceLayout>
</template>
