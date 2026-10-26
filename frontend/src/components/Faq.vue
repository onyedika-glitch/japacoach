<template>
  <section id="faq" class="px-4 sm:px-6 py-20 md:py-28 bg-brand-50/60 scroll-mt-16">
    <div class="max-w-3xl mx-auto">
      <div class="text-center">
        <h4 class="text-brand-dark font-semibold">FAQ</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em]">Questions people ask</h2>
      </div>
      <div class="mt-12 space-y-3">
        <details v-for="item in faqs" :key="item.q" class="card group p-5 open:shadow-md">
          <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-semibold text-[17px]">
            {{ item.q }}
            <ChevronDown class="w-5 h-5 shrink-0 text-muted transition group-open:rotate-180" />
          </summary>
          <p class="mt-3 text-muted leading-relaxed">{{ item.a }}</p>
        </details>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'
import home from '@content/home.json'

// Questions live in api/resources/content/home.json (shared with the markdown version of this page).
const faqs = home.faq.items
</script>

<style scoped>
summary::-webkit-details-marker {
  display: none;
}
</style>
