<script lang="ts">
export default {
  name: 'AppSidebar',
}
</script>

<script setup lang="ts">
import { useTenantStore } from '@/stores/tenant'
import { computed, ref } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useProductStore } from '@/stores/products'
import {
  Squares2X2Icon,
  XMarkIcon,
  HomeIcon,
  ShoppingBagIcon,
  ChevronDownIcon,
  ChartBarIcon,
  EnvelopeIcon,
  ShieldCheckIcon,
  ChevronUpIcon,
  UserIcon,
  Cog6ToothIcon,
  MoonIcon,
  SunIcon,
  ArrowRightEndOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const tenant = useTenantStore()
const uiStore = useUiStore()
const productStore = useProductStore()
const auth = useAuthStore()

const myShopOpen = ref(true)
const accessManagementOpen = ref(false)
const userMenuOpen = ref(false)

const icon = computed(
  () =>
    `https://ui-avatars.com/api/?name=${auth.user?.name}&background=${uiStore.avatarBackground}&color=fff`,
)

const handleLogout = () => {
  productStore.toggleModal('logout', true)
  userMenuOpen.value = false
}
</script>

<template>
  <aside
    :class="uiStore.isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="w-64 bg-white dark:bg-dark-card border-r border-gray-200 dark:border-dark-border flex flex-col fixed h-full z-50 transition-transform duration-300 ease-in-out"
  >
    <div class="p-6 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div
          class="w-8 h-8 bg-teal-500 rounded-lg flex items-center justify-center text-white shadow-lg shadow-teal-500/30"
        >
          <Squares2X2Icon class="w-5 h-5" />
        </div>
        <span class="text-xl font-bold text-gray-900 dark:text-white tracking-tight"
          >Benta Door</span
        >
      </div>
      <button
        @click="uiStore.setSidebar(false)"
        class="lg:hidden inline-flex min-h-11 min-w-11 items-center justify-center text-gray-400 hover:text-gray-600 transition"
        aria-label="Close menu"
        type="button"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
      <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2 mt-4 px-3">
        Main Menu
      </p>
      <router-link
        :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/analytics`"
        class="flex items-center gap-3 min-h-11 px-3 py-2 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 rounded-lg text-sm transition"
        active-class="bg-gray-50 dark:bg-slate-800/50 text-teal-600 dark:text-teal-400"
      >
        <HomeIcon class="w-4 h-4" /> Home
      </router-link>

      <div>
        <button
          @click="myShopOpen = !myShopOpen"
          class="w-full flex items-center justify-between min-h-11 px-3 py-2 rounded-lg text-sm font-medium transition"
          :class="
            myShopOpen
              ? 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-500/10'
              : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50'
          "
          type="button"
        >
          <span class="flex items-center gap-3"><ShoppingBagIcon class="w-4 h-4" /> My Shop</span>
          <ChevronDownIcon
            class="w-4 h-4 transition-transform"
            :class="!myShopOpen && '-rotate-90'"
          />
        </button>
        <div
          v-show="myShopOpen"
          class="pl-10 space-y-1 mt-1 border-l-2 border-teal-500 dark:border-dark-border ml-5"
        >
          <router-link
            :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/products`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Products</router-link
          >
          <router-link
            :to="`/stores/${tenant.store?.slug}`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Order Entry</router-link
          >
          <router-link
            :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/orders`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Sales Transactions</router-link
          >
          <router-link
            :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/customers`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Customers</router-link
          >
        </div>
      </div>

      <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2 mt-6 px-3">
        Shop Management
      </p>
      <router-link
        :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/analytics`"
        class="flex items-center gap-3 min-h-11 px-3 py-2 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white rounded-lg text-sm transition"
        active-class="text-teal-600 dark:text-teal-400 font-bold"
      >
        <ChartBarIcon class="w-4 h-4" /> Analytics
      </router-link>
      <router-link
        to="/vendors"
        class="flex items-center gap-3 min-h-11 px-3 py-2 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white rounded-lg text-sm transition"
        active-class="text-teal-600 dark:text-teal-400 font-bold"
      >
        <EnvelopeIcon class="w-4 h-4" /> Inbox
      </router-link>

      <div class="mt-2">
        <button
          @click="accessManagementOpen = !accessManagementOpen"
          class="w-full flex items-center justify-between min-h-11 px-3 py-2 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white rounded-lg text-sm transition font-medium"
          :class="
            accessManagementOpen
              ? 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-500/10'
              : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50'
          "
          type="button"
        >
          <span class="flex items-center gap-3"
            ><ShieldCheckIcon class="w-4 h-4" /> Access Management</span
          >
          <ChevronDownIcon
            class="w-4 h-4 transition-transform"
            :class="!accessManagementOpen && '-rotate-90'"
          />
        </button>
        <div
          v-show="accessManagementOpen"
          class="pl-10 space-y-1 mt-1 border-l-2 border-teal-500 dark:border-dark-border ml-5"
        >
          <router-link
            :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/users`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Users</router-link
          >
          <router-link
            :to="`/vendors/${encodeURIComponent(tenant.activeVendorSlug)}/roles-permission`"
            class="flex items-center min-h-11 py-2 text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition"
            active-class="text-teal-600 dark:text-teal-400 font-bold"
            >Roles & Permission</router-link
          >
        </div>
      </div>
    </nav>

    <!-- User Profile Dropdown -->
    <div class="p-4 border-t border-gray-100 dark:border-dark-border relative">
      <button
        @click.stop="userMenuOpen = !userMenuOpen"
        class="w-full flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-slate-800/50 p-2 rounded-xl transition relative"
        type="button"
      >
        <img
          :src="icon"
          class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-700 shadow-sm"
          alt="Avatar"
        />
        <div class="flex-1 text-left">
          <p class="text-sm font-bold text-gray-900 dark:text-white leading-none">
            {{ auth.user?.name }}
          </p>
          <p class="text-[11px] text-gray-400 mt-1">{{ auth.user?.roles[0] }}</p>
        </div>
        <ChevronUpIcon
          class="w-4 h-4 text-gray-400 transition-transform"
          :class="userMenuOpen && 'rotate-180'"
        />
      </button>

      <!-- Dropdown Menu -->
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div
          v-if="userMenuOpen"
          v-outside-click="() => (userMenuOpen = false)"
          class="absolute bottom-full left-4 right-4 mb-2 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-2xl rounded-2xl overflow-hidden z-50"
        >
          <div class="p-2 space-y-1">
            <router-link
              :to="{ name: 'profile-settings' }"
              @click="userMenuOpen = false"
              class="flex items-center gap-3 min-h-11 px-3 py-2 text-sm text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 rounded-lg transition"
            >
              <UserIcon class="w-4 h-4" /> Profile Settings
            </router-link>
            <router-link
              :to="{ name: 'account-settings' }"
              @click="userMenuOpen = false"
              class="flex items-center gap-3 min-h-11 px-3 py-2 text-sm text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 rounded-lg transition"
            >
              <Cog6ToothIcon class="w-4 h-4" /> Account Settings
            </router-link>

            <!-- Dark Mode Toggle Inside Dropdown -->
            <button
              @click="uiStore.toggleDarkMode"
              class="w-full flex items-center justify-between min-h-11 px-3 py-2 rounded-lg text-sm font-medium transition-all group hover:bg-gray-50 dark:hover:bg-slate-800"
              type="button"
            >
              <div class="flex items-center gap-3 text-gray-600 dark:text-slate-400">
                <component :is="uiStore.isDarkMode ? MoonIcon : SunIcon" class="w-4 h-4" />
                <span>{{ uiStore.isDarkMode ? 'Dark Mode' : 'Light Mode' }}</span>
              </div>
              <div
                class="w-8 h-4 bg-gray-200 dark:bg-slate-700 rounded-full relative transition-colors"
              >
                <div
                  class="absolute top-0.5 w-3 h-3 bg-white rounded-full transition-all duration-300"
                  :class="uiStore.isDarkMode ? 'left-0.5 translate-x-4 bg-teal-400' : 'left-0.5'"
                ></div>
              </div>
            </button>

            <div class="border-t border-gray-100 dark:border-dark-border my-1"></div>

            <button
              @click="handleLogout"
              class="w-full flex items-center gap-3 min-h-11 px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition"
              type="button"
            >
              <ArrowRightEndOnRectangleIcon class="w-4 h-4" /> Logout
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </aside>
</template>
