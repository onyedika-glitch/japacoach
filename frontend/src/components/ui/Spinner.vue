<template>
  <div class="flex items-center justify-center gap-3 text-muted" :class="block ? 'py-16' : ''" role="status">
    <svg class="animate-spin w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4" />
      <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
    </svg>
    <span v-if="label" class="text-sm">{{ label }}</span>
  </div>
</template>

<script setup lang="ts">
defineProps<{ label?: string; block?: boolean }>()
</script>
