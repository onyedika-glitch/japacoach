<template>
  <div class="px-4 py-12 md:py-20 bg-brand-50/50 min-h-[80vh]">
    <div class="card max-w-md mx-auto p-6 sm:p-8">
      <h1 class="font-grotesk text-2xl font-bold">{{ title }}</h1>
      <p v-if="subtitle" class="text-muted mt-1">{{ subtitle }}</p>
      <div class="mt-6">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>()
</script>
