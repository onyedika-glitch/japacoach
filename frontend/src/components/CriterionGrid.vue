<template>
  <div class="grid sm:grid-cols-2 gap-4">
    <div v-for="(score, key) in scores" :key="key" class="card p-5">
      <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold">{{ criterionLabels[key] ?? key }}</h3>
        <BandBadge :value="score.band" size="sm" />
      </div>
      <p class="mt-2 text-[15px] text-muted leading-relaxed">{{ score.comment }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import BandBadge from '@/components/ui/BandBadge.vue'
import { criterionLabels } from '@/lib/format'
import type { CriterionScore } from '@/lib/types'

defineProps<{ scores: Record<string, CriterionScore> }>()
</script>
