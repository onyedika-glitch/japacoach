<template>
  <ul class="space-y-3">
    <li v-for="(c, i) in corrections" :key="i" class="card p-4">
      <div class="text-bad/80 line-through decoration-bad/60">{{ c.original }}</div>
      <div class="text-good font-medium mt-1 flex gap-2"><ArrowRight class="w-4 h-4 mt-1 shrink-0" />{{ c.corrected }}</div>
      <div class="text-sm text-muted mt-1.5">{{ c.explanation }}</div>
    </li>
    <li v-if="!corrections.length" class="text-muted text-sm">No corrections — nice work.</li>
  </ul>
</template>

<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next'
import type { Correction } from '@/lib/types'

defineProps<{ corrections: Correction[] }>()
</script>
