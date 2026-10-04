<script setup lang="ts">
import { RouterView } from 'vue-router'
import LogoutModal from './components/product/modals/LogoutModal.vue'
import ToastNotifications from '@/components/order-entry/ToastNotifications.vue'
</script>

<template>
  <LogoutModal />
  <ToastNotifications />
  <RouterView :key="$route.params.slug ? String($route.params.slug) : undefined" />
</template>

<style>
/* Global styles can be added here if needed, but we use Tailwind via main.css */
</style>
