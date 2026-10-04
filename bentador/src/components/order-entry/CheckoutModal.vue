<script setup lang="ts">
import { ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'
import {
  XMarkIcon,
  CheckCircleIcon,
  CreditCardIcon,
  BanknotesIcon,
} from '@heroicons/vue/24/outline'
import { useOrderStore } from '@/stores/transactions'
import InlineMessage from '@/components/ui/InlineMessage.vue'

const orderStore = useOrderStore()

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success'): void
}>()

const cartStore = useCartStore()
const toastStore = useToastStore()

const paymentMethods = [
  {
    id: 'cash',
    name: 'Cash',
    icon: BanknotesIcon,
    color: 'text-gray-600 dark:text-slate-300',
    bg: 'bg-gray-50 dark:bg-slate-800/50',
  },
  {
    id: 'bank_transfer',
    name: 'Bank transfer',
    icon: CreditCardIcon,
    color: 'text-teal-600 dark:text-teal-400',
    bg: 'bg-teal-50 dark:bg-teal-500/10',
  },
]
const selectedMethod = ref('cash')
const checkoutError = ref('')

const handleConfirmCheckout = async () => {
  checkoutError.value = ''
  try {
    await orderStore.submitOrder(cartStore.items, {
      currency: 'PHP',
      payment_method: selectedMethod.value,
      shipping_method: 'pickup',
      shipping_fee: 0,
      discount: 0,
      tax: 0,
      notes: '',
    })

    toastStore.addToast('Order Placed Successfully!', 'success')

    cartStore.clearCart()
    cartStore.toggleCart(false)
    emit('success')
    emit('close')
  } catch {
    checkoutError.value =
      'Order could not be placed. Check stock and try again; your cart has been kept.'
  }
}
</script>

<template>
  <BaseModal :show="show" max-width="max-w-md" @close="emit('close')">
    <div class="flex flex-col h-full">
      <!-- Header -->
      <div
        class="px-6 py-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between bg-gray-50/50 dark:bg-slate-800/30"
      >
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-teal-500 rounded-xl flex items-center justify-center text-white shadow-lg shadow-teal-500/20"
          >
            <CreditCardIcon class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-lg font-black text-gray-900 dark:text-white tracking-tight">
              Checkout
            </h2>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
              Select Payment Method
            </p>
          </div>
        </div>
        <button
          type="button"
          class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-white transition active:scale-95"
          @click="emit('close')"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 space-y-6">
        <!-- Order Summary Mini -->
        <div
          class="p-4 bg-gray-50 dark:bg-slate-800/40 rounded-2xl border border-gray-100 dark:border-dark-border"
        >
          <div class="flex justify-between items-center mb-1">
            <span
              class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider"
              >Total Amount</span
            >
            <span class="text-xl font-black text-gray-900 dark:text-white"
              >&#8369;{{ cartStore.cartTotal.toLocaleString() }}</span
            >
          </div>
          <p class="text-[10px] text-gray-400 font-medium italic">
            Final prices are validated by the store.
          </p>
        </div>

        <!-- Payment Methods -->
        <div class="space-y-3">
          <h4
            class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest px-1"
          >
            Payment Options
          </h4>
          <div class="grid grid-cols-1 gap-3">
            <button
              v-for="method in paymentMethods"
              :key="method.id"
              type="button"
              class="relative flex items-center gap-4 p-4 rounded-2xl border-2 transition-all text-left group active:scale-[0.98]"
              :class="[
                selectedMethod === method.id
                  ? 'border-teal-500 bg-teal-50/30 dark:bg-teal-500/5'
                  : 'border-gray-100 dark:border-dark-border bg-white dark:bg-slate-900 hover:border-teal-200',
              ]"
              @click="selectedMethod = method.id"
            >
              <div
                :class="[
                  'w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110',
                  method.bg,
                  method.color,
                ]"
              >
                <component :is="method.icon" class="w-6 h-6" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-black text-gray-900 dark:text-white">{{ method.name }}</p>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                  Confirmed manually by the store
                </p>
              </div>
              <div
                class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
                :class="
                  selectedMethod === method.id
                    ? 'border-teal-500 bg-teal-500 text-white'
                    : 'border-gray-200 dark:border-dark-border'
                "
              >
                <CheckCircleIcon v-if="selectedMethod === method.id" class="w-4 h-4" />
              </div>
            </button>
          </div>
        </div>
      </div>

      <InlineMessage v-if="checkoutError" kind="error" class="mx-6 mb-6">{{
        checkoutError
      }}</InlineMessage>
      <!-- Footer -->
      <div
        class="p-6 border-t border-gray-100 dark:border-dark-border bg-gray-50/50 dark:bg-slate-800/30"
      >
        <button
          type="button"
          :disabled="orderStore.loading"
          class="w-full py-4 bg-teal-500 text-white rounded-2xl font-black text-sm transition-all shadow-xl flex items-center justify-center gap-2 hover:bg-teal-600 shadow-teal-500/30 active:scale-95"
          @click="handleConfirmCheckout"
        >
          Place order — &#8369;{{ cartStore.cartTotal.toLocaleString() }}
        </button>
        <p class="text-center text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-4">
          Payment is pending until confirmed by the store.
        </p>
      </div>
    </div>
  </BaseModal>
</template>
