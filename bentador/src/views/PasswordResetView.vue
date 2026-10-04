<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { EnvelopeIcon, LockClosedIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import AccessLayout from '@/components/layouts/AccessLayout.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
const route = useRoute()
const email = ref(String(route.query.email ?? '')),
  password = ref(''),
  confirmation = ref(''),
  message = ref(''),
  error = ref(''),
  saving = ref(false)
async function submit() {
  error.value = ''
  message.value = ''
  saving.value = true
  try {
    const { data } = route.query.token
      ? await axios.post('/api/v1/auth/reset-password', {
          email: email.value,
          token: route.query.token,
          password: password.value,
          password_confirmation: confirmation.value,
        })
      : await axios.post('/api/v1/auth/forgot-password', { email: email.value })
    message.value = data.message
    password.value = confirmation.value = ''
  } catch {
    error.value =
      'Reset could not complete. Check the email, password confirmation, and link expiry.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AccessLayout
    title="Reset password"
    :description="
      route.query.token
        ? 'Choose a new password to secure your account.'
        : 'Enter your email and we’ll send you a password reset link.'
    "
  >
    <form @submit.prevent="submit" class="space-y-5">
      <FormField
        id="reset-email"
        v-model="email"
        label="Email address"
        type="email"
        :icon="EnvelopeIcon"
        required
        autocomplete="email"
        placeholder="you@example.com"
        :disabled="saving"
      />
      <template v-if="route.query.token">
        <div>
          <FormField
            id="reset-password"
            v-model="password"
            label="New password"
            type="password"
            :icon="LockClosedIcon"
            minlength="12"
            required
            autocomplete="new-password"
            :disabled="saving"
            aria-describedby="password-help"
          />
          <p id="password-help" class="mt-2 text-xs text-gray-500 dark:text-slate-400">
            Use at least 12 characters.
          </p>
        </div>
        <FormField
          id="reset-confirmation"
          v-model="confirmation"
          label="Confirm password"
          type="password"
          :icon="LockClosedIcon"
          required
          autocomplete="new-password"
          :disabled="saving"
        />
      </template>
      <InlineMessage v-if="message" kind="success">{{ message }}</InlineMessage>
      <InlineMessage v-if="error" kind="error">{{ error }}</InlineMessage>
      <ActionButton type="submit" :loading="saving" class="w-full">{{
        saving ? 'Please wait...' : route.query.token ? 'Set password' : 'Send reset link'
      }}</ActionButton>
    </form>
    <template #footer
      ><RouterLink
        to="/login"
        class="inline-flex min-h-11 items-center gap-2 font-bold text-teal-600 dark:text-teal-400 hover:underline"
        ><ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />Back to sign in</RouterLink
      ></template
    >
  </AccessLayout>
</template>
