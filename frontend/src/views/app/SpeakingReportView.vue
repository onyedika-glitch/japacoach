<template>
  <div>
    <RouterLink to="/speaking" class="text-sm text-muted hover:text-ink inline-flex items-center gap-1"><ArrowLeft class="w-4 h-4" /> Speaking</RouterLink>

    <Spinner v-if="loading" block label="Loading report…" />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <template v-else-if="attempt">
      <!-- Processing -->
      <div v-if="attempt.status === 'processing' || attempt.status === 'in_progress'" class="card p-8 sm:p-12 mt-4 text-center">
        <div class="relative mx-auto w-20 h-20">
          <div class="absolute inset-0 rounded-full border-4 border-brand/20" />
          <div class="absolute inset-0 rounded-full border-4 border-brand border-t-transparent animate-spin" />
          <Mic class="absolute inset-0 m-auto w-8 h-8 text-brand" />
        </div>
        <h1 class="font-grotesk text-2xl font-bold mt-6">Your examiner is marking…</h1>
        <p class="text-muted mt-2 max-w-md mx-auto">
          We're transcribing every answer and checking fluency, vocabulary, grammar and pronunciation. A full mock can take a few minutes — you can
          leave this page and come back; we'll email you when it's ready.
        </p>
        <div class="mt-6 text-sm text-muted">{{ transcribedCount }} of {{ attempt.questions?.length ?? 0 }} answers transcribed</div>
        <RouterLink v-if="attempt.status === 'in_progress'" :to="`/speaking/test/${attempt.id}`" class="btn btn-primary mt-6">Continue test</RouterLink>
      </div>

      <!-- Failed -->
      <div v-else-if="attempt.status === 'failed'" class="card p-8 mt-4 text-center">
        <AlertTriangle class="w-10 h-10 mx-auto text-bad" />
        <h1 class="font-grotesk text-xl font-bold mt-3">We couldn't mark this test</h1>
        <p class="text-muted mt-2">Something went wrong while processing your audio. This attempt won't count toward your limit. Please try again.</p>
        <RouterLink to="/speaking" class="btn btn-primary mt-6">Start a new test</RouterLink>
      </div>

      <!-- Scored -->
      <template v-else>
        <div class="mt-4 card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6">
          <div class="text-center sm:text-left">
            <div class="text-sm text-muted">Estimated band</div>
            <BandBadge :value="attempt.overall_band" size="lg" class="mt-2" />
          </div>
          <div class="flex-1">
            <h1 class="font-grotesk text-2xl font-bold">{{ modeTitle }}</h1>
            <p class="text-sm text-muted">{{ dateTime(attempt.scored_at ?? attempt.created_at) }}</p>
            <p v-if="attempt.feedback?.summary" class="mt-3 text-[15px] leading-relaxed">{{ attempt.feedback.summary }}</p>
          </div>
        </div>
        <p class="text-xs text-muted mt-2">Estimated band based on the public IELTS speaking descriptors — not an official result.</p>

        <section v-if="attempt.scores" class="mt-8">
          <h2 class="section-title mb-3">Your four criteria</h2>
          <CriterionGrid :scores="attempt.scores" />
        </section>

        <section v-if="metrics" class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="card p-4">
            <div class="text-xs text-muted">Words per minute</div>
            <div class="font-grotesk text-2xl font-bold">{{ Math.round(metrics.words_per_minute) }}</div>
          </div>
          <div class="card p-4">
            <div class="text-xs text-muted">Long pauses</div>
            <div class="font-grotesk text-2xl font-bold">{{ metrics.long_pauses }}</div>
          </div>
          <div class="card p-4">
            <div class="text-xs text-muted">Filler words</div>
            <div class="font-grotesk text-2xl font-bold">{{ metrics.filler_words }}</div>
          </div>
          <div class="card p-4">
            <div class="text-xs text-muted">Speaking time</div>
            <div class="font-grotesk text-2xl font-bold">{{ clock(metrics.total_speaking_seconds) }}</div>
          </div>
        </section>

        <section v-if="attempt.feedback" class="mt-8 grid md:grid-cols-2 gap-4">
          <BulletList title="What you did well" :items="attempt.feedback.strengths" :icon="ThumbsUp" icon-class="text-good" dot-class="bg-good" />
          <BulletList title="What to work on" :items="attempt.feedback.improvements" :icon="Target" icon-class="text-accent" dot-class="bg-accent" />
        </section>

        <section v-if="attempt.feedback?.corrections?.length" class="mt-8">
          <h2 class="section-title mb-3">Fix these sentences</h2>
          <CorrectionList :corrections="attempt.feedback.corrections" />
        </section>

        <section v-if="attempt.feedback?.pronunciation_tips?.length" class="mt-8">
          <h2 class="section-title mb-3">Pronunciation tips</h2>
          <div class="grid sm:grid-cols-2 gap-3">
            <div v-for="t in attempt.feedback.pronunciation_tips" :key="t.word" class="card p-4">
              <div class="font-semibold">{{ t.word }}</div>
              <div class="text-sm text-muted mt-1">{{ t.tip }}</div>
            </div>
          </div>
        </section>

        <section v-if="attempt.feedback?.better_answers?.length" class="mt-8">
          <h2 class="section-title mb-3">Stronger versions of your answers</h2>
          <div class="space-y-3">
            <details v-for="(b, i) in attempt.feedback.better_answers" :key="i" class="card p-4 group" :open="i === 0">
              <summary class="cursor-pointer list-none font-medium flex justify-between gap-3">
                {{ b.question }} <ChevronDown class="w-5 h-5 shrink-0 text-muted group-open:rotate-180 transition" />
              </summary>
              <p class="mt-3 text-[15px] leading-relaxed bg-good/5 rounded-xl p-3">{{ b.answer }}</p>
            </details>
          </div>
        </section>

        <section v-if="attempt.questions?.length" class="mt-8">
          <h2 class="section-title mb-3">Your answers</h2>
          <div class="space-y-3">
            <div v-for="q in attempt.questions" :key="q.id" class="card p-4">
              <div class="text-xs font-semibold text-brand-dark">Part {{ q.part }}</div>
              <div class="font-medium mt-0.5">{{ q.prompt }}</div>
              <audio v-if="q.answer?.audio_url" :src="q.answer.audio_url" controls preload="none" class="w-full mt-3 h-10" />
              <p v-if="q.answer?.transcript" class="mt-2 text-[15px] text-muted leading-relaxed">“{{ q.answer.transcript }}”</p>
              <p v-else class="mt-2 text-sm text-muted italic">No transcript.</p>
            </div>
          </div>
        </section>

        <div class="mt-10 flex flex-col sm:flex-row gap-3">
          <RouterLink to="/speaking" class="btn btn-primary"><RotateCcw class="w-4 h-4" /> Practise again</RouterLink>
          <RouterLink to="/study-plan" class="btn btn-secondary">Update my study plan</RouterLink>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AlertTriangle, ArrowLeft, ChevronDown, Mic, RotateCcw, Target, ThumbsUp } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import CriterionGrid from '@/components/CriterionGrid.vue'
import CorrectionList from '@/components/CorrectionList.vue'
import BulletList from '@/components/BulletList.vue'
import { api, errorMessage } from '@/lib/api'
import { clock, dateTime } from '@/lib/format'
import { usePolling } from '@/composables/usePolling'
import type { SpeakingAttempt } from '@/lib/types'

const route = useRoute()
const id = Number(route.params.id)
const attempt = ref<SpeakingAttempt | null>(null)
const loading = ref(true)
const error = ref('')

const modeTitle = computed(
  () => ({ full: 'Full speaking mock', part1: 'Part 1 drill', part2: 'Part 2 drill', part3: 'Part 3 drill' })[attempt.value?.mode ?? 'full'],
)
const metrics = computed(() => attempt.value?.feedback?.metrics ?? null)
const transcribedCount = computed(() => attempt.value?.questions?.filter((q) => q.answer?.status === 'transcribed').length ?? 0)

async function load() {
  const { data } = await api.get<{ attempt: SpeakingAttempt }>(`/api/speaking/attempts/${id}`)
  attempt.value = data.attempt
}

const polling = usePolling(load, () => attempt.value?.status === 'processing', 5000)

onMounted(async () => {
  try {
    await load()
    polling.start()
  } catch (e) {
    error.value = errorMessage(e, 'Could not load this report.')
  } finally {
    loading.value = false
  }
})
</script>
