<template>
  <div>
    <div class="flex items-center justify-between text-sm">
      <span class="font-medium">{{ label }}</span>
      <span class="text-muted">{{ usage.limit === null ? `${usage.used} used · unlimited` : `${usage.used} / ${usage.limit}` }}</span>
    </div>
    <div class="mt-1.5 h-2 rounded-full bg-gray-100 overflow-hidden">
      <div class="h-full rounded-full transition-all" :class="full ? 'bg-accent' : 'bg-brand'" :style="{ width: pct + '%' }" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Usage } from '@/lib/types'

const props = defineProps<{ label: string; usage: Usage }>()
const pct = computed(() => (props.usage.limit === null ? 8 : props.usage.limit === 0 ? 100 : Math.min(100, (props.usage.used / props.usage.limit) * 100)))
const full = computed(() => props.usage.limit !== null && props.usage.used >= props.usage.limit)
</script>
