<template>
  <figure class="card p-4 sm:p-5">
    <figcaption class="font-semibold text-[15px] mb-3">{{ visual.title }}<span v-if="unit" class="text-muted font-normal"> ({{ unit }})</span></figcaption>

    <div v-if="visual.type === 'table'" class="overflow-x-auto">
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr>
            <th v-for="c in visual.columns" :key="c" class="text-left font-semibold bg-brand-50 border border-gray-200 px-3 py-2">{{ c }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in visual.rows" :key="i">
            <td v-for="(cell, j) in row" :key="j" class="border border-gray-200 px-3 py-2">{{ cell }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <ChartBox v-else :type="visual.type" :data="chartData" :options="options" :height="300" />
  </figure>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import ChartBox from '@/components/ui/ChartBox.vue'
import { seriesColors } from '@/lib/palette'
import type { TaskVisual } from '@/lib/types'

const props = defineProps<{ visual: TaskVisual }>()

const unit = computed(() => (props.visual.type === 'table' ? '' : props.visual.unit ?? ''))

const chartData = computed(() => {
  const v = props.visual
  if (v.type === 'table') return { labels: [], datasets: [] }
  if (v.type === 'pie') {
    return {
      labels: v.labels,
      datasets: v.datasets.map((d) => ({
        label: d.label,
        data: d.data,
        backgroundColor: v.labels.map((_, i) => seriesColors[i % seriesColors.length]),
        borderColor: '#fff',
        borderWidth: 2,
      })),
    }
  }
  return {
    labels: v.labels,
    datasets: v.datasets.map((d, i) => {
      const color = seriesColors[i % seriesColors.length]
      return {
        label: d.label,
        data: d.data,
        backgroundColor: v.type === 'bar' ? color : color + '22',
        borderColor: color,
        borderWidth: 2,
        pointRadius: 3,
        tension: 0.2,
        borderRadius: v.type === 'bar' ? 4 : 0,
      }
    }),
  }
})

const options = computed(() =>
  props.visual.type === 'pie'
    ? {}
    : {
        scales: {
          y: { beginAtZero: true, grid: { color: '#f1f5f9' }, title: { display: !!unit.value, text: unit.value } },
          x: { grid: { display: false } },
        },
      },
)
</script>
