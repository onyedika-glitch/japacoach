<template>
  <div class="card p-5">
    <h3 class="font-semibold flex items-center gap-2">
      <component :is="icon" class="w-5 h-5" :class="iconClass" /> {{ title }}
    </h3>
    <ul class="mt-3 space-y-2 text-[15px]">
      <li v-for="(item, i) in items" :key="i" class="flex gap-2">
        <span class="mt-2 w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass" />
        <span>{{ item }}</span>
      </li>
      <li v-if="!items.length" class="text-muted">None yet</li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{ title: string; items: string[]; icon: Component; iconClass?: string; dotClass?: string }>()
</script>
