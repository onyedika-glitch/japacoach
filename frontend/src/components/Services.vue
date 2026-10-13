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
import { BookOpen, CalendarCheck, ClipboardList, Headphones, LineChart, Mic, PenLine } from 'lucide-vue-next'

const brand = 'bg-brand-50 text-brand-dark'
const accent = 'bg-accent/15 text-accent'

const services = [
  { icon: Mic, tint: brand, title: 'Speaking', text: 'Full 3-part mock with an AI examiner voice, cue cards with prep time, and quick daily drills.' },
  { icon: PenLine, tint: brand, title: 'Writing', text: 'Task 1 (charts, tables, letters) and Task 2 essays with a timer, word count, corrections and a model answer.' },
  { icon: BookOpen, tint: brand, title: 'Reading', text: 'Academic and General passages with every common question type, auto-marked with explanations.' },
  { icon: Headphones, tint: brand, title: 'Listening', text: 'Four sections in British and American voices, auto-marked, with transcripts after you finish.' },
  { icon: ClipboardList, tint: accent, title: 'Full mock exam', text: 'Listening → Reading → Writing → Speaking, with an overall band. The closest thing to test day.' },
  { icon: CalendarCheck, tint: accent, title: 'Study plan', text: 'A week-by-week plan built from your test date, target band and weakest skills.' },
  { icon: LineChart, tint: accent, title: 'Progress tracking', text: 'Bands over time for every module, streaks and your recent attempts in one dashboard.' },
]
</script>
