<template>
  <section id="how" class="px-4 sm:px-6 py-20 md:py-28 bg-brand-50/60 scroll-mt-16">
    <div class="max-w-6xl mx-auto">
      <div class="text-center max-w-2xl mx-auto">
        <h4 class="text-brand-dark font-semibold">How it works</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em]">Three steps. Every day.</h2>
      </div>

      <div class="mt-14 grid md:grid-cols-3 gap-6">
        <div v-for="(step, i) in steps" :key="step.title" class="card p-7 relative">
          <div class="font-grotesk text-6xl font-bold text-brand/15 absolute right-6 top-4">{{ i + 1 }}</div>
          <div class="w-12 h-12 rounded-xl bg-brand text-white grid place-items-center">
            <component :is="step.icon" class="w-6 h-6" />
          </div>
          <h3 class="mt-5 font-grotesk text-xl font-semibold">{{ step.title }}</h3>
          <p class="mt-2 text-muted">{{ step.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { Mic, Gauge, TrendingUp } from 'lucide-vue-next'

const steps = [
  {
    icon: Mic,
    title: 'Speak or write',
    text: 'Answer real-format questions on your phone: a speaking test with an examiner voice, a timed essay, or a reading or listening paper.',
  },
  {
    icon: Gauge,
    title: 'Get your estimated band',
    text: 'See a band for every criterion, your mistakes highlighted, and stronger versions of your own answers.',
  },
  {
    icon: TrendingUp,
    title: 'Fix and improve',
    text: 'Practise the weak areas your study plan picks for you, then retake. Watch your band climb toward your target.',
  },
]
</script>
