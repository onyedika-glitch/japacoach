<template>
  <div class="relative w-full" :style="{ height: height + 'px' }">
    <component :is="chartComponent" :data="data" :options="mergedOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
  Title,
  type ChartData,
  type ChartOptions,
} from 'chart.js'
import { Bar, Line, Pie } from 'vue-chartjs'

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Tooltip, Legend, Filler, Title)
ChartJS.defaults.font.family = "'Figtree', system-ui, sans-serif"

const props = withDefaults(
  defineProps<{
    type: 'bar' | 'line' | 'pie'
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data: ChartData<any>
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    options?: ChartOptions<any>
    height?: number
  }>(),
  { height: 280, options: () => ({}) },
)

const chartComponent = computed(() => ({ bar: Bar, line: Line, pie: Pie })[props.type])

const mergedOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const, labels: { boxWidth: 12 } } },
  ...props.options,
}))
</script>
