<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  UserCircleIcon,
  LockClosedIcon,
  BuildingStorefrontIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import { useAuthStore } from '@/stores/auth'
import AccessLayout from '@/components/layouts/AccessLayout.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
const route = useRoute(),
  auth = useAuthStore()
const invite = ref<{ store: string; email: string; existing_account: boolean } | null>(null)
const name = ref(''),
  password = ref(''),
  confirmation = ref(''),
  message = ref(''),
  error = ref(''),
  loading = ref(true),
  saving = ref(false)
const path = `/api/v1/invitations/${route.params.token}`
onMounted(async () => {
  try {
    const { data } = await axios.get(path)
    invite.value = data.data
  } catch {
    error.value = 'Invitation expired or unavailable.'
  } finally {
    loading.value = false
  }
})
async function accept() {
  error.value = ''
  saving.value = true
  try {
    const { data } = await axios.post(
      `${path}/accept`,
      invite.value?.existing_account
        ? {}
        : { name: name.value, password: password.value, password_confirmation: confirmation.value },
    )
    message.value = data.message
    password.value = confirmation.value = ''
  } catch {
    error.value = 'Invitation could not be accepted. Check your account and password confirmation.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AccessLayout
    title="Store invitation"
    description="Join your team and start managing your store."
  >
    <p
      v-if="loading"
      role="status"
      class="text-sm text-gray-500 dark:text-slate-400 py-6 text-center"
    >
      Loading invitation...
    </p>
    <InlineMessage v-if="error" kind="error" class="mb-5">{{ error }}</InlineMessage>
    <InlineMessage v-if="message" kind="success">{{ message }}</InlineMessage>
    <form v-if="invite && !message" @submit.prevent="accept" class="space-y-5">
      <div
        class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-slate-800/50 border border-gray-100 dark:border-dark-border rounded-2xl"
      >
        <BuildingStorefrontIcon
          class="w-6 h-6 text-teal-600 dark:text-teal-400 shrink-0"
          aria-hidden="true"
        />
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-900 dark:text-white break-words">
            {{ invite.store }}
          </p>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 break-all">
            Invited as {{ invite.email }}
          </p>
        </div>
      </div>
      <template v-if="!invite.existing_account">
        <FormField
          id="invite-name"
          v-model="name"
          label="Full name"
          :icon="UserCircleIcon"
          required
          autocomplete="name"
          :disabled="saving"
        />
        <div>
          <FormField
            id="invite-password"
            v-model="password"
            label="Password"
            type="password"
            :icon="LockClosedIcon"
            minlength="12"
            required
            autocomplete="new-password"
            :disabled="saving"
            aria-describedby="invite-password-help"
          />
          <p id="invite-password-help" class="text-xs text-gray-500 dark:text-slate-400 mt-2">
            Use at least 12 characters.
          </p>
        </div>
        <FormField
          id="invite-confirmation"
          v-model="confirmation"
          label="Confirm password"
          type="password"
          :icon="LockClosedIcon"
          required
          autocomplete="new-password"
          :disabled="saving"
        />
      </template>
      <RouterLink
        v-else-if="!auth.user || auth.user.email !== invite.email"
        :to="`/login?redirect=${encodeURIComponent(route.fullPath)}`"
        class="flex min-h-11 items-center justify-center px-5 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95"
        >Sign in to your invited account</RouterLink
      >
      <ActionButton
        v-if="!invite.existing_account || auth.user?.email === invite.email"
        type="submit"
        :loading="saving"
        class="w-full"
        >{{ saving ? 'Joining store...' : 'Accept invitation' }}</ActionButton
      >
    </form>
    <RouterLink
      v-if="message && auth.user"
      to="/vendors"
      class="flex min-h-11 items-center justify-center px-5 py-3 mt-5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95"
      >Go to your stores</RouterLink
    >
    <template #footer
      ><RouterLink
        to="/login"
        class="inline-flex items-center gap-2 min-h-11 font-bold text-teal-600 dark:text-teal-400 hover:underline"
        ><ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />Sign in</RouterLink
      ></template
    >
  </AccessLayout>
</template>
