<template>
  <article class="max-w-3xl mx-auto px-4 sm:px-6 py-14 legal">
    <h1 class="page-title">Terms of use</h1>
    <p class="text-[16px] leading-relaxed text-ink/90 mt-2">Last updated: October 2026</p>
    <LegalBody :source="source" />
  </article>
</template>

<script setup lang="ts">
import LegalBody from '@/components/LegalBody.vue'
import source from '@content/terms.md?raw'
</script>
