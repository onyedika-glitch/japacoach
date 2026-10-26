<template>
  <section class="px-4 sm:px-6 py-20 md:py-28 overflow-hidden">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
      <div>
        <h4 class="text-brand-dark font-semibold">How you're scored</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em] leading-tight">
          Marked on the same four criteria as real examiners
        </h2>
        <p class="mt-5 text-lg text-muted">
          Your answers are transcribed, measured for pace and pauses, then marked against the public IELTS band descriptors. You get a band for each
          criterion, plus the exact sentences to fix.
        </p>

        <div class="relative mt-10 hidden md:grid place-items-center h-56">
          <svg ref="orbit" viewBox="0 0 200 200" class="w-56 h-56" aria-hidden="true">
            <circle cx="100" cy="100" r="80" fill="none" stroke="#01B8C6" stroke-opacity=".35" stroke-width="2" stroke-dasharray="6 8" />
            <circle cx="100" cy="100" r="50" fill="none" stroke="#F5A524" stroke-opacity=".5" stroke-width="2" />
            <circle cx="100" cy="20" r="9" fill="#01B8C6" />
            <circle cx="180" cy="100" r="7" fill="#F5A524" />
            <circle cx="100" cy="150" r="6" fill="#01B8C6" fill-opacity=".6" />
          </svg>
          <div class="absolute font-grotesk text-4xl font-bold text-ink">7.0</div>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div v-for="(card, i) in cards" :key="card.title" class="crit card p-5" :class="i === 4 ? 'sm:col-span-2 bg-brand-50 border-brand/20' : ''">
          <component :is="card.icon" class="w-7 h-7 text-brand" />
          <h3 class="mt-3 font-grotesk font-semibold text-lg">{{ card.title }}</h3>
          <p class="mt-1.5 text-sm text-muted leading-relaxed">{{ card.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import home from '@content/home.json'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { gsap } from 'gsap'
import { AudioLines, BookA, SpellCheck, Waves, Zap } from 'lucide-vue-next'

const orbit = ref<SVGElement | null>(null)
let tween: gsap.core.Tween | null = null

// Text lives in api/resources/content/home.json (shared with the markdown version of this page).
const icons = [Waves, BookA, SpellCheck, AudioLines, Zap]
const cards = home.scoring.cards.map((card, i) => ({ ...card, icon: icons[i] }))

onMounted(() => {
  if (orbit.value && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    tween = gsap.to(orbit.value, { rotation: 360, duration: 20, repeat: -1, ease: 'linear', transformOrigin: '50% 50%' })
  }
})
onBeforeUnmount(() => tween?.kill())
</script>

<style scoped>
.crit {
  transition: transform 0.3s ease;
}
.crit:hover {
  transform: translateY(-4px);
}
</style>
