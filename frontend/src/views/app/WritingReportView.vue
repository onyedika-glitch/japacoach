<template>
  <div>
    <RouterLink to="/writing" class="text-sm text-muted hover:text-ink inline-flex items-center gap-1"><ArrowLeft class="w-4 h-4" /> Writing</RouterLink>

    <Spinner v-if="loading" block label="Loading report…" />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <template v-else-if="attempt">
      <div v-if="attempt.status === 'processing' || attempt.status === 'in_progress'" class="card p-8 sm:p-12 mt-4 text-center">
        <div class="relative mx-auto w-20 h-20">
          <div class="absolute inset-0 rounded-full border-4 border-brand/20" />
          <div class="absolute inset-0 rounded-full border-4 border-brand border-t-transparent animate-spin" />
          <PenLine class="absolute inset-0 m-auto w-8 h-8 text-brand" />
        </div>
        <h1 class="font-grotesk text-2xl font-bold mt-6">Your examiner is marking…</h1>
        <p class="text-muted mt-2">This usually takes under a minute.</p>
      </div>

      <div v-else-if="attempt.status === 'failed'" class="card p-8 mt-4 text-center">
        <AlertTriangle class="w-10 h-10 mx-auto text-bad" />
        <h1 class="font-grotesk text-xl font-bold mt-3">We couldn't mark this essay</h1>
        <p class="text-muted mt-2">Please try submitting again. This attempt won't count toward your limit.</p>
        <RouterLink :to="`/writing/task/${attempt.task.id}`" class="btn btn-primary mt-6">Try again</RouterLink>
      </div>

      <template v-else>
        <div class="mt-4 card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6">
          <div class="text-center sm:text-left">
            <div class="text-sm text-muted">Estimated band</div>
            <BandBadge :value="attempt.overall_band" size="lg" class="mt-2" />
          </div>
          <div class="flex-1">
            <div class="chip bg-brand-50 text-brand-dark">Task {{ attempt.task.task_number }}</div>
            <h1 class="font-grotesk text-2xl font-bold mt-2">{{ attempt.task.title }}</h1>
            <p class="text-sm text-muted">{{ attempt.word_count }} words · {{ dateTime(attempt.created_at) }}</p>
            <p v-if="attempt.feedback?.summary" class="mt-3 text-[15px] leading-relaxed">{{ attempt.feedback.summary }}</p>
          </div>
        </div>
        <p class="text-xs text-muted mt-2">Estimated band based on the public IELTS writing descriptors. Not an official result.</p>

        <section v-if="attempt.scores" class="mt-8">
          <h2 class="section-title mb-3">Your four criteria</h2>
          <CriterionGrid :scores="attempt.scores" />
        </section>

        <section v-if="attempt.feedback" class="mt-8 grid md:grid-cols-2 gap-4">
          <BulletList title="What you did well" :items="attempt.feedback.strengths" :icon="ThumbsUp" icon-class="text-good" dot-class="bg-good" />
          <BulletList title="What to work on" :items="attempt.feedback.improvements" :icon="Target" icon-class="text-accent" dot-class="bg-accent" />
        </section>

        <section class="mt-8 grid lg:grid-cols-2 gap-6 items-start">
          <div>
            <h2 class="section-title mb-3">Your response</h2>
            <div class="card p-5 text-[15px] leading-relaxed whitespace-pre-line">{{ attempt.essay }}</div>
          </div>
          <div>
            <h2 class="section-title mb-3">Corrections</h2>
            <CorrectionList :corrections="attempt.feedback?.corrections ?? []" />
          </div>
        </section>

        <section v-if="attempt.feedback?.model_answer" class="mt-8">
          <h2 class="section-title mb-3">Model answer</h2>
          <div class="card p-5 bg-good/5 border-good/20 text-[15px] leading-relaxed whitespace-pre-line">{{ attempt.feedback.model_answer }}</div>
        </section>

        <section class="mt-8">
          <details class="card p-5">
            <summary class="cursor-pointer font-semibold">Show the task again</summary>
            <p class="mt-3 text-[15px] whitespace-pre-line">{{ attempt.task.prompt }}</p>
            <TaskVisual v-if="attempt.task.visual" :visual="attempt.task.visual" class="mt-3" />
          </details>
        </section>

        <div class="mt-10 flex flex-col sm:flex-row gap-3">
          <RouterLink :to="`/writing/task/${attempt.task.id}`" class="btn btn-primary"><RotateCcw class="w-4 h-4" /> Rewrite this task</RouterLink>
          <RouterLink to="/writing" class="btn btn-secondary">Try another task</RouterLink>
        </div>

        <ReviewPrompt module="writing" />
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import ReviewPrompt from '@/components/ReviewPrompt.vue'
import { useRoute } from 'vue-router'
import { AlertTriangle, ArrowLeft, PenLine, RotateCcw, Target, ThumbsUp } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import CriterionGrid from '@/components/CriterionGrid.vue'
import CorrectionList from '@/components/CorrectionList.vue'
import BulletList from '@/components/BulletList.vue'
import TaskVisual from '@/components/TaskVisual.vue'
import { api, errorMessage } from '@/lib/api'
import { dateTime } from '@/lib/format'
import { usePolling } from '@/composables/usePolling'
import type { WritingAttempt } from '@/lib/types'

const route = useRoute()
const id = Number(route.params.id)
const attempt = ref<WritingAttempt | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  const { data } = await api.get<{ attempt: WritingAttempt }>(`/api/writing/attempts/${id}`)
  attempt.value = data.attempt
}

const polling = usePolling(load, () => attempt.value?.status === 'processing' || attempt.value?.status === 'in_progress', 4000)

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
