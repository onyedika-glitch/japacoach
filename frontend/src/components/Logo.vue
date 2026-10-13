<template>
  <RouterLink :to="to" class="flex items-center gap-2 select-none" aria-label="Japa Coach home">
    <svg viewBox="0 0 64 64" class="w-9 h-9 shrink-0" aria-hidden="true">
      <rect width="64" height="64" rx="16" fill="#01B8C6" />
      <path d="M20 40c0 6 4 9 9 9 6 0 9-4 9-10V15h-8v24c0 2-1 3-2 3s-2-1-2-3z" fill="#fff" />
      <path d="M42 15l8 8-8 8" fill="none" stroke="#F5A524" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="font-grotesk font-bold text-xl tracking-tight text-ink">
      Japa<span class="text-brand">Coach</span>
    </span>
  </RouterLink>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ to?: string }>(), { to: '/' })
</script>
