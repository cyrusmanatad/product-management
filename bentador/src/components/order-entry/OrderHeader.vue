<script setup lang="ts">
import { storefrontSlug } from '@/utils/tenantContext'
import { computed, ref } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import {
  Bars3Icon,
  MagnifyingGlassIcon,
  ChevronDownIcon,
  SunIcon,
  MoonIcon,
  ShoppingCartIcon,
  ClipboardDocumentListIcon,
  TruckIcon,
  ArrowRightEndOnRectangleIcon,
  ChartBarIcon,
  UserIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

defineProps<{
  searchQuery: string
  sortBy: string
}>()

const emit = defineEmits<{
  (e: 'update:searchQuery', value: string): void
  (e: 'update:sortBy', value: string): void
  (e: 'open-sidebar'): void
  (e: 'logout'): void
  (e: 'sign-in'): void
}>()

const uiStore = useUiStore()
const cartStore = useCartStore()
const authStore = useAuthStore()

const icon = computed(
  () =>
    `https://ui-avatars.com/api/?name=${authStore.user?.name}&background=${uiStore.avatarBackground}&color=fff`,
)

const userOpen = ref(false)
</script>

<template>
  <header
    class="bg-white/80 dark:bg-dark-card/80 backdrop-blur-md sticky top-0 z-30 border-b border-gray-200 dark:border-dark-border px-4 lg:px-8 py-4"
  >
    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-3 sm:gap-4 lg:gap-8">
      <!-- Mobile Toggle & Logo -->
      <div class="flex items-center gap-3 shrink-0">
        <button
          type="button"
          class="lg:hidden p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-xl transition active:scale-95"
          @click="emit('open-sidebar')"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>
        <RouterLink to="/" aria-label="Benta Door home" class="flex items-center gap-2">
          <div
            class="w-8 h-8 bg-teal-500 rounded-lg flex items-center justify-center text-white shadow-lg shadow-teal-500/30"
          >
            <ShoppingCartIcon class="w-5 h-5" />
          </div>
          <span
            class="text-lg font-black text-gray-900 dark:text-white tracking-tighter hidden sm:block"
            >Benta Door</span
          >
        </RouterLink>
      </div>

      <!-- Search Bar -->
      <div
        class="order-last sm:order-none w-full sm:w-auto sm:flex-1 min-w-0 relative max-w-2xl mx-auto"
      >
        <MagnifyingGlassIcon
          class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
        />
        <input
          type="text"
          :value="searchQuery"
          placeholder="Search for products, categories..."
          class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-slate-900/50 border border-gray-200 dark:border-dark-border rounded-2xl text-sm focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none transition dark:text-white"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 lg:gap-4 shrink-0">
        <!-- Sorting -->
        <div class="hidden md:block relative group">
          <select
            :value="sortBy"
            class="appearance-none pl-4 pr-10 py-2.5 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold focus:ring-4 focus:ring-teal-500/10 outline-none cursor-pointer transition"
            @change="emit('update:sortBy', ($event.target as HTMLSelectElement).value)"
          >
            <option value="default">Sort by: Default</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
            <option value="title-az">Name: A-Z</option>
          </select>
          <ChevronDownIcon
            class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
          />
        </div>

        <button
          type="button"
          class="p-2.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-xl transition active:scale-95"
          @click="uiStore.toggleDarkMode"
        >
          <SunIcon v-if="uiStore.isDarkMode" class="w-5 h-5" />
          <MoonIcon v-else class="w-5 h-5" />
        </button>

        <button
          type="button"
          class="relative p-2.5 bg-teal-500 text-white rounded-xl shadow-lg shadow-teal-500/20 hover:bg-teal-600 transition active:scale-95 flex items-center gap-2"
          aria-label="Open cart"
          @click="cartStore.toggleCart(true)"
        >
          <ShoppingCartIcon class="w-5 h-5" />
          <span
            v-if="cartStore.cartCount > 0"
            class="absolute -top-1 -right-1 w-5 h-5 bg-orange-500 text-white text-[10px] flex items-center justify-center rounded-full border-2 border-white dark:border-dark-bg font-black"
          >
            {{ cartStore.cartCount }}
          </span>
          <span class="hidden xl:inline text-xs font-bold"
            >&#8369;{{ cartStore.cartTotal.toLocaleString() }}</span
          >
        </button>

        <div class="h-8 w-[1px] bg-gray-200 dark:bg-dark-border mx-1 hidden sm:block"></div>

        <button
          v-if="!authStore.user"
          type="button"
          class="min-h-11 px-4 py-2.5 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border text-gray-900 dark:text-white rounded-xl text-xs font-black hover:border-teal-500 hover:text-teal-600 dark:hover:text-teal-400 transition active:scale-95"
          @click="emit('sign-in')"
        >
          Sign in
        </button>

        <!-- User Profile Dropdown -->
        <div v-else class="relative">
          <button
            type="button"
            class="flex items-center gap-3 p-1 rounded-2xl hover:bg-gray-100 dark:hover:bg-slate-800 transition active:scale-95"
            @click.stop="userOpen = !userOpen"
          >
            <div
              class="w-10 h-10 rounded-xl bg-gradient-to-tr from-teal-600 to-teal-500 p-[2px] shadow-lg shadow-teal-500/20"
            >
              <img
                :src="icon"
                class="w-full h-full rounded-[10px] object-cover border-2 border-white dark:border-dark-card"
                alt="Avatar"
              />
            </div>
            <div class="text-left hidden sm:block">
              <p class="text-xs font-black text-gray-900 dark:text-white leading-tight">
                {{ authStore.user?.name || 'Juan Dela Cruz' }}
              </p>
              <p class="text-[10px] text-gray-400 font-bold">
                {{ authStore.user?.roles[0] || 'Customer' }}
              </p>
            </div>
            <ChevronDownIcon
              class="w-4 h-4 text-gray-400 transition-transform hidden sm:block"
              :class="userOpen && 'rotate-180'"
            />
          </button>

          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="userOpen"
              class="absolute right-0 mt-2 w-56 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-2xl rounded-2xl z-50 py-2 overflow-hidden"
              v-outside-click="() => (userOpen = false)"
            >
              <div
                class="px-4 py-3 border-b dark:border-dark-border bg-gray-50/50 dark:bg-slate-800/30 sm:hidden"
              >
                <p class="text-xs font-black text-gray-900 dark:text-white">
                  {{ authStore.user?.name || 'Juan Dela Cruz' }}
                </p>
                <p class="text-[10px] text-gray-400 font-bold">Customer</p>
              </div>
              <router-link
                :to="`/stores/${storefrontSlug}/orders`"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
              >
                <ClipboardDocumentListIcon
                  class="w-4 h-4 text-gray-400 group-hover:text-teal-500"
                />
                Order Summary
              </router-link>
              <router-link
                :to="`/stores/${storefrontSlug}/orders`"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
              >
                <TruckIcon class="w-4 h-4 text-gray-400 group-hover:text-teal-500" /> Track Order
              </router-link>
              <div class="h-[1px] bg-gray-100 dark:bg-dark-border my-1"></div>

              <router-link
                v-if="authStore.hasRole('Super Admin')"
                to="/vendors"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
                ><ChartBarIcon class="w-4 h-4 text-gray-400 group-hover:text-teal-500" />
                Dashboard
              </router-link>
              <!-- <a
                href="#"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
              >
                
              </a> -->

              <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
                @click="uiStore.toggleDarkMode"
              >
                <div class="flex items-center gap-3">
                  <SunIcon v-if="uiStore.isDarkMode" class="w-4 h-4 text-gray-400" />
                  <MoonIcon v-else class="w-4 h-4 text-gray-400" />
                  <span>{{ uiStore.isDarkMode ? 'Light Mode' : 'Dark Mode' }}</span>
                </div>
                <div class="relative inline-flex items-center cursor-pointer scale-75">
                  <div
                    class="w-11 h-6 bg-gray-200 dark:bg-teal-500/20 rounded-full transition-colors"
                    :class="uiStore.isDarkMode ? 'bg-teal-500' : 'bg-gray-200'"
                  ></div>
                  <div
                    class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm"
                    :class="uiStore.isDarkMode ? 'translate-x-5' : 'translate-x-0'"
                  ></div>
                </div>
              </button>

              <router-link
                to="/profile"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
                @click="userOpen = false"
              >
                <UserIcon class="w-4 h-4 text-gray-400 group-hover:text-teal-500" />
                Profile Settings
              </router-link>
              <router-link
                to="/account"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition group"
                @click="userOpen = false"
              >
                <Cog6ToothIcon class="w-4 h-4 text-gray-400 group-hover:text-teal-500" />
                Account Settings
              </router-link>

              <div class="h-[1px] bg-gray-100 dark:bg-dark-border my-1"></div>
              <button
                type="button"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition group"
                @click="emit('logout')"
              >
                <ArrowRightEndOnRectangleIcon class="w-4 h-4" /> Logout
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </header>
</template>
