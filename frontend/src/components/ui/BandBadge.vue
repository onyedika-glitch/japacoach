<template>
  <span class="inline-flex items-center justify-center rounded-xl font-grotesk font-bold" :class="[sizeClass, colorClass]">
    {{ band(value) }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { band } from '@/lib/format'

const props = withDefaults(defineProps<{ value: number | null | undefined; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' })

const sizeClass = computed(
  () =>
    ({
      sm: 'min-w-10 h-8 px-2 text-sm',
      md: 'min-w-14 h-12 px-3 text-xl',
      lg: 'min-w-24 h-20 px-4 text-5xl',
    })[props.size],
)

const colorClass = computed(() => {
  const v = props.value
  if (v === null || v === undefined) return 'bg-gray-100 text-muted'
  if (v >= 7) return 'bg-good/10 text-good'
  if (v >= 5.5) return 'bg-brand-50 text-brand-dark'
  return 'bg-accent/15 text-[#b4730a]'
})
</script>
