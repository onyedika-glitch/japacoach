<template>
  <section class="px-4 sm:px-6 pt-6">
    <div
      class="relative max-w-7xl mx-auto overflow-hidden bg-brand-50 rounded-3xl md:rounded-[4rem] border-2 border-dashed border-brand/30 px-5 sm:px-10 pt-14 md:pt-24 pb-12 md:pb-20 text-center"
    >
      <div class="absolute inset-0 bg-grid opacity-60 pointer-events-none" />

      <!-- Rotating shape (desktop) -->
      <svg ref="shape" viewBox="0 0 120 120" class="hidden md:block absolute right-10 top-10 w-28 h-28 opacity-80" aria-hidden="true">
        <rect x="25" y="25" width="70" height="70" rx="14" fill="none" stroke="#01B8C6" stroke-width="4" />
        <rect x="35" y="35" width="50" height="50" rx="10" fill="#01B8C6" fill-opacity=".12" stroke="#F5A524" stroke-width="3" />
      </svg>
      <svg ref="shape2" viewBox="0 0 120 120" class="hidden md:block absolute left-12 bottom-16 w-20 h-20 opacity-70" aria-hidden="true">
        <circle cx="60" cy="60" r="40" fill="none" stroke="#01B8C6" stroke-width="4" stroke-dasharray="14 10" />
      </svg>

      <div class="relative max-w-3xl mx-auto">
        <h1 class="font-figtree tracking-[-0.02em] text-[2.4rem] leading-[1.1] sm:text-6xl md:text-7xl font-bold text-ink">
          Hit your <span class="text-brand">IELTS band</span><br class="hidden sm:block" />
          before your test date.
        </h1>

        <p class="mt-6 text-lg md:text-xl text-muted max-w-2xl mx-auto">
          Practise Speaking, Writing, Reading &amp; Listening with an AI examiner. Get an estimated band score and specific fixes in
          minutes. Built for Nigerians preparing to relocate.
        </p>

        <div class="mt-9 flex flex-col sm:flex-row justify-center gap-3 sm:gap-4">
          <RouterLink to="/signup" class="btn btn-primary h-14 px-7 text-lg">
            <Mic class="w-5 h-5" /> Take a free speaking test
          </RouterLink>
          <RouterLink to="/pricing" class="btn btn-secondary h-14 px-7 text-lg">See pricing</RouterLink>
        </div>
        <p class="mt-4 text-sm text-muted">No card needed · Works on your phone</p>
      </div>

      <!-- Sample report preview -->
      <div class="relative mt-12 md:mt-16 max-w-3xl mx-auto text-left">
        <div class="card p-5 sm:p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-sm text-muted">Speaking mock · sample report</div>
              <div class="font-grotesk text-lg font-semibold">Your estimated band</div>
            </div>
            <div class="font-grotesk text-5xl font-bold text-brand">6.5</div>
          </div>
          <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div v-for="c in sample" :key="c.label" class="rounded-xl bg-brand-50 p-3">
              <div class="text-xs text-muted leading-tight">{{ c.label }}</div>
              <div class="font-grotesk text-2xl font-bold mt-1">{{ c.band }}</div>
            </div>
          </div>
          <div class="mt-5 rounded-xl border border-gray-100 p-4 text-sm">
            <div class="line-through text-bad/80">"I am living in Lagos since 2019."</div>
            <div class="text-good font-medium mt-1">"I have been living in Lagos since 2019."</div>
            <div class="text-muted mt-1">Use the present perfect continuous for an action that started in the past and continues now.</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { gsap } from 'gsap'
import { Mic } from 'lucide-vue-next'

const shape = ref<SVGElement | null>(null)
const shape2 = ref<SVGElement | null>(null)
let tweens: gsap.core.Tween[] = []

const sample = [
  { label: 'Fluency & Coherence', band: '6.5' },
  { label: 'Lexical Resource', band: '6.0' },
  { label: 'Grammar', band: '6.5' },
  { label: 'Pronunciation', band: '7.0' },
]

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  if (shape.value) tweens.push(gsap.to(shape.value, { rotation: 360, repeat: -1, duration: 16, ease: 'linear', transformOrigin: '50% 50%' }))
  if (shape2.value) tweens.push(gsap.to(shape2.value, { rotation: -360, repeat: -1, duration: 22, ease: 'linear', transformOrigin: '50% 50%' }))
})

onBeforeUnmount(() => {
  tweens.forEach((t) => t.kill())
  tweens = []
})
</script>
