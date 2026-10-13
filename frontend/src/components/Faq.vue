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

const faqs = [
  {
    q: 'Is the band score official?',
    a: 'No. It is an estimate based on the public IELTS band descriptors. Only an official IELTS test gives you an official result. Our estimates are designed to show you where you stand and exactly what to fix.',
  },
  {
    q: 'Are you connected to IELTS, the British Council or IDP?',
    a: 'No. Japa Coach is an independent practice tool. We are not affiliated with IELTS, the British Council, IDP or Cambridge.',
  },
  {
    q: 'Do I need a laptop?',
    a: 'No. Everything works in your phone browser, including the speaking test — you just need to allow microphone access.',
  },
  {
    q: 'Academic or General Training?',
    a: 'Both. Choose your test type in settings and we will show you the right Writing Task 1 and Reading papers. Speaking and Listening are the same for both.',
  },
  {
    q: 'How long does marking take?',
    a: 'Writing and reading results come back within a minute or two. A full speaking mock can take a few minutes because every answer is transcribed and analysed — we will show your report as soon as it is ready.',
  },
  {
    q: 'How do I pay? Will you debit my card every month?',
    a: 'You pay once for a fixed number of days of access through our payment partner, Bachs. There are no automatic renewals — we will remind you before your access ends.',
  },
  {
    q: 'What happens to my recordings?',
    a: 'Your audio is used only to mark your answers. Recordings are deleted automatically after 30 days; your scores and transcripts stay in your history.',
  },
]
</script>

<style scoped>
summary::-webkit-details-marker {
  display: none;
}
</style>
