<script setup lang="ts">
import { ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import CheckoutModal from '@/components/order-entry/CheckoutModal.vue'
import {
  XMarkIcon,
  ShoppingBagIcon,
  MinusIcon,
  PlusIcon,
  CreditCardIcon,
  ShoppingCartIcon,
} from '@heroicons/vue/24/outline'

const cartStore = useCartStore()
const authStore = useAuthStore()

const isCheckoutModalOpen = ref(false)

const emit = defineEmits<{
  (e: 'require-auth'): void
}>()

const openCheckout = () => {
  isCheckoutModalOpen.value = true
}

const handleCheckout = () => {
  if (!authStore.user) {
    emit('require-auth')
    return
  }

  openCheckout()
}

defineExpose({ openCheckout })
</script>

<template>
  <!-- Checkout Modal -->
  <CheckoutModal :show="isCheckoutModalOpen" @close="isCheckoutModalOpen = false" />

  <div v-show="cartStore.isCartOpen" class="fixed inset-0 z-[100] overflow-hidden">
    <!-- BACKDROP -->
    <Transition
      enter-active-class="transition-opacity ease-linear duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-linear duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="cartStore.isCartOpen"
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        @click="cartStore.toggleCart(false)"
      ></div>
    </Transition>

    <div class="fixed inset-y-0 right-0 max-w-full flex">
      <Transition
        enter-active-class="transform transition ease-in-out duration-500"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transform transition ease-in-out duration-500"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
      >
        <div v-if="cartStore.isCartOpen" class="w-screen max-w-md">
          <div class="h-full flex flex-col bg-white dark:bg-dark-card shadow-2xl">
            <div
              class="px-6 py-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between bg-gray-50/50 dark:bg-slate-800/30"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-teal-500 rounded-xl flex items-center justify-center text-white shadow-lg shadow-teal-500/20"
                >
                  <ShoppingBagIcon class="w-5 h-5" />
                </div>
                <div>
                  <h2 class="text-lg font-black text-gray-900 dark:text-white tracking-tight">
                    Shopping Cart
                  </h2>
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                    {{ cartStore.items.length }} Items Selected
                  </p>
                </div>
              </div>
              <button
                type="button"
                class="p-2 text-gray-400 hover:text-gray-600 transition active:scale-95"
                @click="cartStore.toggleCart(false)"
              >
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
              <div class="space-y-4">
                <div
                  v-for="item in cartStore.items"
                  :key="item.variant_id"
                  class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-slate-800/40 rounded-3xl border border-gray-100 dark:border-dark-border group transition-all"
                >
                  <div
                    class="w-16 h-16 bg-white dark:bg-slate-700 rounded-2xl flex items-center justify-center text-gray-400 shadow-inner group-hover:scale-105 transition-transform"
                  >
                    <ShoppingCartIcon class="w-8 h-8" />
                  </div>
                  <div class="flex-1">
                    <h4
                      class="text-sm font-bold text-gray-900 dark:text-white line-clamp-1"
                      :title="item.title"
                    >
                      {{ item.title }}
                    </h4>
                    <p class="text-xs text-teal-600 dark:text-teal-400 font-black mt-0.5">
                      &#8369;{{ item.price.toLocaleString() }}
                    </p>

                    <div class="flex items-center gap-3 mt-3">
                      <div
                        class="flex items-center bg-white dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl px-1"
                      >
                        <button
                          class="p-1 text-gray-400 hover:text-red-500 transition active:scale-95"
                          @click="cartStore.updateQuantity(item.variant_id, -1)"
                        >
                          <MinusIcon class="w-3 h-3" />
                        </button>
                        <span class="w-8 text-center text-xs font-bold dark:text-white">{{
                          item.quantity
                        }}</span>
                        <button
                          class="p-1 text-gray-400 hover:text-teal-500 transition active:scale-95"
                          @click="cartStore.updateQuantity(item.variant_id, 1)"
                        >
                          <PlusIcon class="w-3 h-3" />
                        </button>
                      </div>
                      <button
                        class="text-[10px] font-bold text-red-500 hover:underline uppercase tracking-wider"
                        @click="cartStore.removeFromCart(item.variant_id)"
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-black text-gray-900 dark:text-white">
                      &#8369;{{ (item.price * item.quantity).toLocaleString() }}
                    </p>
                  </div>
                </div>

                <div
                  v-if="cartStore.items.length === 0"
                  class="py-20 flex flex-col items-center justify-center text-center opacity-50"
                >
                  <div
                    class="w-20 h-20 bg-gray-100 dark:bg-slate-800 rounded-3xl flex items-center justify-center text-gray-300 mb-4 shadow-inner"
                  >
                    <ShoppingCartIcon class="w-10 h-10" />
                  </div>
                  <p class="text-sm font-black text-gray-500">Your cart is empty</p>
                  <p class="text-xs text-gray-400 mt-1">Add items to get started</p>
                </div>
              </div>
            </div>

            <div
              class="p-6 border-t border-gray-100 dark:border-dark-border bg-gray-50/50 dark:bg-slate-800/30"
            >
              <div class="space-y-3 mb-6">
                <div
                  class="flex justify-between text-gray-500 dark:text-slate-400 text-xs font-bold"
                >
                  <span>Subtotal</span>
                  <span>&#8369;{{ cartStore.cartTotal.toLocaleString() }}</span>
                </div>
                <div
                  class="flex justify-between text-gray-500 dark:text-slate-400 text-xs font-bold"
                >
                  <span>Shipping</span>
                  <span class="text-teal-500">FREE</span>
                </div>
                <div class="h-[1px] bg-gray-200 dark:bg-dark-border my-2"></div>
                <div class="flex justify-between text-gray-900 dark:text-white text-xl font-black">
                  <span>Order Total</span>
                  <span>&#8369;{{ cartStore.cartTotal.toLocaleString() }}</span>
                </div>
              </div>
              <button
                type="button"
                :disabled="cartStore.items.length === 0"
                class="w-full py-4 bg-teal-500 text-white rounded-2xl font-black text-sm transition-all shadow-xl flex items-center justify-center gap-2"
                :class="
                  cartStore.items.length === 0
                    ? 'opacity-50 cursor-not-allowed'
                    : 'hover:bg-teal-600 shadow-teal-500/30 active:scale-95'
                "
                @click="handleCheckout"
              >
                <CreditCardIcon class="w-5 h-5" /> Checkout Now
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>
