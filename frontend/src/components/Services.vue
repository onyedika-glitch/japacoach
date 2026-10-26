<template>
  <section id="modules" class="px-4 sm:px-6 py-20 md:py-28 scroll-mt-16">
    <div class="max-w-6xl mx-auto">
      <div class="text-center max-w-2xl mx-auto">
        <h4 class="text-brand-dark font-semibold">Everything in one place</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em]">All four modules, plus a coach</h2>
        <p class="mt-4 text-lg text-muted">Academic and General Training. Practise one skill at a time, or sit a full mock exam.</p>
      </div>

      <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="s in services" :key="s.title" class="card p-6 hover:-translate-y-1 transition">
          <div class="w-11 h-11 rounded-xl grid place-items-center" :class="s.tint">
            <component :is="s.icon" class="w-6 h-6" />
          </div>
          <h3 class="mt-4 font-grotesk text-lg font-semibold">{{ s.title }}</h3>
          <p class="mt-1.5 text-muted text-[15px] leading-relaxed">{{ s.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import home from '@content/home.json'
import { BookOpen, CalendarCheck, ClipboardList, Headphones, LineChart, Mic, PenLine } from 'lucide-vue-next'

const brand = 'bg-brand-50 text-brand-dark'
const accent = 'bg-accent/15 text-accent'

// Text lives in api/resources/content/home.json (shared with the markdown version of this page).
const look = [
  { icon: Mic, tint: brand },
  { icon: PenLine, tint: brand },
  { icon: BookOpen, tint: brand },
  { icon: Headphones, tint: brand },
  { icon: ClipboardList, tint: accent },
  { icon: CalendarCheck, tint: accent },
  { icon: LineChart, tint: accent },
]
const services = home.modules.items.map((s, i) => ({ ...s, ...look[i] }))
</script>
