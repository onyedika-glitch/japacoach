<template>
  <div class="rounded-xl p-4 text-sm flex gap-3" :class="classes">
    <component :is="icon" class="w-5 h-5 shrink-0 mt-0.5" />
    <div class="flex-1"><slot /></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next'

const props = withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'error' }>(), { tone: 'info' })

const classes = computed(
  () =>
    ({
      info: 'bg-brand-50 text-ink',
      success: 'bg-good/10 text-ink',
      warning: 'bg-accent/10 text-ink',
      error: 'bg-bad/10 text-ink',
    })[props.tone],
)
const icon = computed(() => ({ info: Info, success: CheckCircle2, warning: AlertTriangle, error: XCircle })[props.tone])
</script>
