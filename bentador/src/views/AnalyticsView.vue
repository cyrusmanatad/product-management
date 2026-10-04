<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import type { ApexOptions } from 'apexcharts'
import { useUiStore } from '@/stores/ui'
import {
  Bars3Icon,
  CalendarIcon,
  ChevronDownIcon,
  DocumentTextIcon,
  CurrencyDollarIcon,
  ShoppingCartIcon,
  MagnifyingGlassIcon,
  GlobeAltIcon,
  ShareIcon,
  TableCellsIcon,
  DocumentArrowDownIcon,
  ArrowUpIcon,
  ArrowDownIcon,
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/common/BaseModal.vue'
import { useAnalyticsStore } from '@/stores/analyticsStore'

const uiStore = useUiStore()
const analyticsStore = useAnalyticsStore()
const timeRange = ref('Last 30 Days')
const timeRangeOpen = ref(false)
const exportModal = ref(false)

const CHART_COLORS = ['#F97316', '#94A3B8', '#9413B0', '#F94316', '#3B82F6', '#EAB308', '#EC4899']

const chartPalette = computed(() => {
  const lead =
    uiStore.colorTheme === 'green' ? '#0D9488' : uiStore.isDarkMode ? '#ADE1FB' : '#266CA9'
  return [lead, ...CHART_COLORS]
})

const kpis = computed(() => {
  const k = analyticsStore.kpi

  return [
    {
      label: 'Collected payments',
      val: k ? `₱${k.net_revenue.value}` : 'n/a',
      trend: k?.net_revenue.trend.label ?? 'n/a',
      up: k?.net_revenue.trend.direction === 'up',
      icon: CurrencyDollarIcon,
      color: 'text-green-500',
    },
    {
      label: 'Avg. Order Value',
      val: k ? `₱${k.avg_order_value.value}` : 'n/a',
      trend: k?.avg_order_value.trend.label ?? 'n/a',
      up: k?.avg_order_value.trend.direction === 'up',
      color: 'text-green-500',
      icon: ShoppingCartIcon,
    },
  ]
})

const trafficSources = [
  {
    name: 'Google Search',
    vis: '12,402',
    bounce: '24.2%',
    rev: '$42,100',
    icon: MagnifyingGlassIcon,
  },
  { name: 'Direct Traffic', vis: '8,102', bounce: '12.5%', rev: '$31,500', icon: GlobeAltIcon },
  { name: 'Social Media', vis: '5,420', bounce: '42.1%', rev: '$12,800', icon: ShareIcon },
]

const revenueChartOptions = computed<ApexOptions>(() => {
  const isDark = uiStore.isDarkMode
  const textColor = isDark ? '#94a3b8' : '#64748b'
  const gridColor = isDark ? (uiStore.colorTheme === 'blue' ? '#3175B0' : '#1e293b') : '#f1f5f9'

  return {
    chart: {
      height: 300,
      type: 'area',
      toolbar: { show: false },
      background: 'transparent',
    },
    colors: [chartPalette.value[0] ?? '#0D9488', '#cbd5e1'],
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 },
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    grid: { borderColor: gridColor },
    xaxis: {
      categories: analyticsStore.revenue?.categories ?? [
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat',
        'Sun',
      ],
      labels: { style: { colors: textColor } },
    },
    yaxis: {
      labels: {
        style: { colors: textColor },
        formatter: (val: number) => `${val.toLocaleString()}`, // format Y axis
      },
    },
    tooltip: {
      y: {
        formatter: (val: number) =>
          `₱${val.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
          })}`,
      },
    },
    theme: { mode: isDark ? 'dark' : 'light' },
    legend: { show: false },
  }
})

const revenueSeries = computed(() => [
  {
    name: 'Current Week',
    data: analyticsStore.revenue?.current ?? [0, 0, 0, 0, 0, 0, 0],
  },
  {
    name: 'Previous Week',
    data: analyticsStore.revenue?.previous ?? [0, 0, 0, 0, 0, 0, 0],
  },
])

const categoryChartOptions = computed<ApexOptions>(() => {
  const isDark = uiStore.isDarkMode
  const textColor = isDark ? '#94a3b8' : '#64748b'
  const gridColor = isDark ? (uiStore.colorTheme === 'blue' ? '#3175B0' : '#1e293b') : '#f1f5f9'

  return {
    chart: {
      height: 300,
      type: 'bar',
      toolbar: { show: false },
      background: 'transparent',
    },
    colors: chartPalette.value,
    plotOptions: {
      bar: {
        horizontal: true,
        borderRadius: 6,
        barHeight: '60%',
        distributed: true,
      },
    },
    dataLabels: { enabled: false },
    legend: { show: false },
    grid: { borderColor: gridColor },
    xaxis: {
      categories: analyticsStore.categories?.labels ?? [],
      labels: {
        style: { colors: textColor },
        formatter: (val: string) => {
          const amount = Number(val)
          return Number.isFinite(amount) ? amount.toLocaleString('en-PH') : val
        },
      },
    },
    yaxis: {
      labels: { style: { colors: textColor } },
    },
    tooltip: {
      y: {
        formatter: (val: number) =>
          `₱${val.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
          })}`,
      },
    },
    theme: { mode: isDark ? 'dark' : 'light' },
  }
})

const categorySeries = computed(() => [
  {
    name: 'Revenue',
    data: analyticsStore.categories?.series ?? [],
  },
])

const handleTimerange = (range: string) => {
  timeRange.value = range
  timeRangeOpen.value = false
}

onMounted(() => analyticsStore.fetchAll())
</script>

<template>
  <div class="p-4 md:p-8">
    <!-- HEADER -->
    <header
      class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4"
    >
      <div class="flex items-center gap-4">
        <button
          @click="uiStore.setSidebar(true)"
          class="lg:hidden p-2 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-xl"
          type="button"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics Overview</h1>
          <p class="text-gray-500 dark:text-slate-400 text-sm">Real-time store performance data</p>
        </div>
      </div>
      <div class="flex gap-2">
        <div class="relative">
          <button
            @click="timeRangeOpen = !timeRangeOpen"
            class="bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border px-4 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
            type="button"
          >
            <CalendarIcon class="w-4 h-4 text-teal-500" />
            <span>{{ timeRange }}</span>
            <ChevronDownIcon
              class="w-4 h-4 text-gray-400 transition-transform"
              :class="timeRangeOpen && 'rotate-180'"
            />
          </button>
          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="timeRangeOpen"
              v-outside-click="() => (timeRangeOpen = false)"
              class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-xl rounded-xl overflow-hidden z-50"
            >
              <button
                v-for="range in ['Today', 'Last 7 Days', 'Last 30 Days', 'Last 12 Months']"
                :key="range"
                @click="handleTimerange(range)"
                class="w-full text-left px-4 py-2.5 text-sm text-gray-600 dark:text-slate-400 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition"
                type="button"
              >
                {{ range }}
              </button>
            </div>
          </Transition>
        </div>
        <button
          @click="exportModal = true"
          class="bg-teal-500 hover:bg-teal-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg shadow-teal-500/20 transition-all active:scale-95"
          type="button"
        >
          <DocumentTextIcon class="w-4 h-4" /> Export
        </button>
      </div>
    </header>

    <!-- KPI GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div
        v-for="kpi in kpis"
        :key="kpi.label"
        class="bg-white dark:bg-dark-card p-6 rounded-2xl border border-gray-100 dark:border-dark-border shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex justify-between items-start mb-4">
          <div
            class="p-2.5 bg-teal-50 dark:bg-teal-500/10 rounded-xl text-teal-600 dark:text-teal-400"
          >
            <component :is="kpi.icon" class="w-5 h-5" />
          </div>
          <span
            class="text-[10px] font-black px-2 py-1 rounded-lg bg-gray-50 dark:bg-slate-800"
            :class="kpi.color"
          >
            {{ kpi.trend }}
          </span>
        </div>
        <p class="text-xs text-gray-500 dark:text-slate-400 font-medium mb-1">{{ kpi.label }}</p>
        <h3 class="text-2xl font-black text-gray-900 dark:text-white">{{ kpi.val }}</h3>
      </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <!-- Main Sales Chart -->
      <div
        class="lg:col-span-2 bg-white dark:bg-dark-card p-6 rounded-2xl border border-gray-100 dark:border-dark-border shadow-sm"
      >
        <div class="flex justify-between items-center mb-6">
          <div>
            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">
              Revenue Performance
            </h3>
            <!-- Dynamic growth indicator -->
            <p
              class="text-xs mt-1 flex"
              :class="(analyticsStore.revenue?.growth || 0) >= 0 ? 'text-teal-500' : 'text-red-400'"
            >
              <span>
                <ArrowUpIcon v-if="(analyticsStore.revenue?.growth || 0) >= 0" class="w-4 h-4" />
                <ArrowDownIcon v-else class="w-4 h-4" />
              </span>
              {{ Math.abs(analyticsStore.revenue?.growth ?? 0) }}% vs last week
            </p>
          </div>

          <div class="flex gap-4">
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400">
              <span class="w-2 h-2 rounded-full bg-teal-500"></span>
              Current (₱{{ analyticsStore.revenue?.current_total ?? '0.00' }})
            </span>
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400">
              <span class="w-2 h-2 rounded-full bg-slate-300"></span>
              Previous (₱{{ analyticsStore.revenue?.prev_total ?? '0.00' }})
            </span>
          </div>
        </div>

        <!-- Loading state -->
        <div v-if="analyticsStore.loading" class="h-[300px] flex items-center justify-center">
          <div
            class="w-8 h-8 border-[3px] border-gray-200 dark:border-slate-700 border-t-teal-500 rounded-full animate-spin"
          />
        </div>

        <VueApexCharts
          v-else
          type="area"
          height="300"
          :options="revenueChartOptions"
          :series="revenueSeries"
        />
      </div>

      <!-- Category Distribution -->
      <div
        class="bg-white dark:bg-dark-card p-6 rounded-2xl border border-gray-100 dark:border-dark-border shadow-sm"
      >
        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-6">
          Top Categories
        </h3>

        <div
          v-if="analyticsStore.loading"
          class="h-[300px] flex flex-col items-center justify-center gap-3"
        >
          <div
            class="w-8 h-8 border-[3px] border-gray-200 dark:border-slate-700 border-t-teal-500 rounded-full animate-spin"
            role="status"
          />
          <p class="text-sm text-gray-600 dark:text-slate-400">Loading category sales</p>
        </div>

        <p
          v-else-if="analyticsStore.categoryError"
          class="text-center text-sm text-gray-600 dark:text-slate-400 py-8"
        >
          {{ analyticsStore.categoryError }}
        </p>

        <p
          v-else-if="!analyticsStore.categories?.categories?.length"
          class="text-center text-sm text-gray-600 dark:text-slate-400 py-8"
        >
          No category sales yet. Record an order to see how revenue splits across categories.
        </p>

        <template v-else>
          <VueApexCharts
            type="bar"
            height="300"
            :options="categoryChartOptions"
            :series="categorySeries"
          />

          <div class="mt-4 space-y-3">
            <div
              v-for="(cat, index) in analyticsStore.categories?.categories ?? []"
              :key="cat.name"
              class="flex justify-between items-center text-xs"
            >
              <div class="flex items-center gap-2">
                <span
                  class="w-2 h-2 rounded-full flex-shrink-0"
                  :style="{ backgroundColor: chartPalette[index % chartPalette.length] }"
                />
                <span class="text-gray-500 dark:text-slate-400 font-medium">
                  {{ cat.name }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-gray-600 dark:text-slate-400 text-[10px]">
                  {{ cat.total_orders }} orders
                </span>
                <span class="font-black text-gray-900 dark:text-white">
                  {{ cat.percentage }}%
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- RECENT ACTIVITY / SECONDARY DATA -->
    <div
      v-show="false"
      class="bg-white dark:bg-dark-card rounded-2xl border border-gray-200 dark:border-dark-border shadow-sm overflow-hidden"
    >
      <div class="p-6 border-b dark:border-dark-border flex justify-between items-center">
        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">
          Traffic Sources
        </h3>
        <button
          class="text-xs font-bold text-teal-600 dark:text-teal-400 hover:underline"
          type="button"
        >
          View Full Report
        </button>
      </div>
      <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left">
          <thead>
            <tr
              class="bg-gray-50/50 dark:bg-slate-800/20 text-[10px] uppercase tracking-widest text-gray-400 font-bold border-b dark:border-dark-border"
            >
              <th class="px-6 py-4">Source</th>
              <th class="px-6 py-4">Visitors</th>
              <th class="px-6 py-4">Bounce Rate</th>
              <th class="px-6 py-4 text-right">Revenue Contributed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
            <tr
              v-for="src in trafficSources"
              :key="src.name"
              class="hover:bg-gray-50/80 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-slate-800 flex items-center justify-center text-gray-400"
                  >
                    <component :is="src.icon" class="w-4 h-4" />
                  </div>
                  <span class="text-sm font-bold text-gray-900 dark:text-white">{{
                    src.name
                  }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm font-medium">{{ src.vis }}</td>
              <td class="px-6 py-4 text-sm font-medium text-gray-500">{{ src.bounce }}</td>
              <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white">
                {{ src.rev }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Export Modal -->
    <BaseModal :show="exportModal" @close="exportModal = false" maxWidth="max-w-md">
      <div class="p-8 text-center">
        <div
          class="w-20 h-20 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-full flex items-center justify-center mx-auto mb-6"
        >
          <DocumentArrowDownIcon class="w-10 h-10" />
        </div>
        <h2 class="text-xl font-black text-gray-900 dark:text-white mb-2">Export Data</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mb-8">
          Select your preferred format for the analytics report.
        </p>
        <div class="grid grid-cols-2 gap-3 mb-6">
          <button
            class="p-4 border dark:border-dark-border rounded-2xl hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:border-teal-200 transition group"
            type="button"
          >
            <TableCellsIcon class="w-6 h-6 mx-auto mb-2 text-gray-400 group-hover:text-teal-500" />
            <span class="text-xs font-bold block">Excel (.xlsx)</span>
          </button>
          <button
            class="p-4 border dark:border-dark-border rounded-2xl hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:border-teal-200 transition group"
            type="button"
          >
            <DocumentTextIcon
              class="w-6 h-6 mx-auto mb-2 text-gray-400 group-hover:text-teal-500"
            />
            <span class="text-xs font-bold block">PDF Report</span>
          </button>
        </div>
        <button
          @click="exportModal = false"
          class="w-full py-3 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400 font-bold rounded-2xl"
          type="button"
        >
          Cancel
        </button>
      </div>
    </BaseModal>
  </div>
</template>
