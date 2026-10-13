<template>
  <div>
    <RouterLink :to="attempt ? `/${attempt.module}` : '/dashboard'" class="text-sm text-muted hover:text-ink inline-flex items-center gap-1">
      <ArrowLeft class="w-4 h-4" /> {{ attempt ? moduleLabels[attempt.module] : 'Back' }}
    </RouterLink>

    <Spinner v-if="loading" block label="Loading results…" />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <template v-else-if="attempt">
      <div v-if="attempt.status !== 'submitted'" class="card p-8 mt-4 text-center">
        <p class="text-muted">This test hasn't been submitted yet.</p>
        <RouterLink :to="`/${attempt.module}/test/${attempt.id}`" class="btn btn-primary mt-4">Continue test</RouterLink>
      </div>

      <template v-else>
        <div class="mt-4 card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6">
          <div class="text-center sm:text-left">
            <div class="text-sm text-muted">Estimated band</div>
            <BandBadge :value="attempt.band" size="lg" class="mt-2" />
          </div>
          <div class="flex-1">
            <h1 class="font-grotesk text-2xl font-bold">{{ attempt.test.title }}</h1>
            <p class="text-sm text-muted">{{ dateTime(attempt.submitted_at) }}</p>
            <div class="mt-3 font-grotesk text-3xl font-bold">
              {{ attempt.correct_count ?? 0 }}<span class="text-muted text-xl"> / {{ attempt.total_count }} correct</span>
            </div>
          </div>
        </div>
        <p class="text-xs text-muted mt-2">Band estimated from your raw score using standard IELTS conversion tables, scaled to 40 questions.</p>

        <div class="mt-6 flex gap-2">
          <button
            v-for="f in filters"
            :key="f.key"
            class="px-3 h-9 rounded-lg text-sm font-semibold border"
            :class="filter === f.key ? 'bg-ink text-white border-ink' : 'bg-white border-gray-200 text-muted'"
            @click="filter = f.key"
          >
            {{ f.label }}
          </button>
        </div>

        <div v-for="s in sections" :key="s.id" class="mt-6">
          <h2 class="section-title">{{ s.title }}</h2>

          <details v-if="s.transcript?.length" class="card p-4 mt-3">
            <summary class="cursor-pointer font-semibold text-sm">Show transcript</summary>
            <div class="mt-3 space-y-2 text-[15px]">
              <p v-for="(line, i) in s.transcript" :key="i"><strong class="text-brand-dark">{{ line.speaker }}:</strong> {{ line.text }}</p>
            </div>
          </details>
          <details v-else-if="s.body" class="card p-4 mt-3">
            <summary class="cursor-pointer font-semibold text-sm">Show passage</summary>
            <p v-for="(para, i) in s.body.split(/\n\s*\n/)" :key="i" class="mt-3 text-[15px] leading-relaxed">{{ para }}</p>
          </details>

          <div class="mt-3 space-y-3">
            <template v-for="q in s.questions" :key="q.id">
              <div v-if="showRow(q.id)" class="card p-4" :class="reviewFor(q.id)?.is_correct ? 'border-good/30' : 'border-bad/30'">
                <div class="flex gap-3">
                  <component
                    :is="reviewFor(q.id)?.is_correct ? CheckCircle2 : XCircle"
                    class="w-5 h-5 shrink-0 mt-0.5"
                    :class="reviewFor(q.id)?.is_correct ? 'text-good' : 'text-bad'"
                  />
                  <div class="flex-1 min-w-0">
                    <div class="text-[15px]"><strong>{{ q.number }}.</strong> {{ q.prompt }}</div>
                    <div class="mt-2 text-sm grid sm:grid-cols-2 gap-1">
                      <div>
                        <span class="text-muted">Your answer: </span>
                        <span :class="reviewFor(q.id)?.is_correct ? 'text-good font-semibold' : 'text-bad font-semibold'">{{ formatAnswer(reviewFor(q.id)?.your_answer) }}</span>
                      </div>
                      <div v-if="!reviewFor(q.id)?.is_correct">
                        <span class="text-muted">Correct: </span>
                        <span class="font-semibold">{{ (reviewFor(q.id)?.correct_answers ?? []).join(' / ') }}</span>
                      </div>
                    </div>
                    <p v-if="reviewFor(q.id)?.explanation" class="mt-2 text-sm text-muted bg-gray-50 rounded-lg p-2.5">{{ reviewFor(q.id)?.explanation }}</p>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <div class="mt-10 flex flex-col sm:flex-row gap-3">
          <RouterLink :to="`/${attempt.module}`" class="btn btn-primary">Try another test</RouterLink>
          <RouterLink to="/dashboard" class="btn btn-secondary">Back to dashboard</RouterLink>
        </div>

        <ReviewPrompt :module="attempt.module" />
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import ReviewPrompt from '@/components/ReviewPrompt.vue'
import { api, errorMessage } from '@/lib/api'
import { dateTime, moduleLabels } from '@/lib/format'
import type { AnswerValue, PracticeAttempt } from '@/lib/types'

const route = useRoute()
const id = Number(route.params.attemptId)
const attempt = ref<PracticeAttempt | null>(null)
const loading = ref(true)
const error = ref('')
const filter = ref<'all' | 'wrong'>('all')
const filters = [
  { key: 'all' as const, label: 'All questions' },
  { key: 'wrong' as const, label: 'Only mistakes' },
]

const sections = computed(() => [...(attempt.value?.test.sections ?? [])].sort((a, b) => a.position - b.position))
const reviewMap = computed(() => new Map((attempt.value?.review ?? []).map((r) => [r.question_id, r])))

function reviewFor(id: number) {
  return reviewMap.value.get(id)
}
function showRow(id: number) {
  return filter.value === 'all' || !reviewFor(id)?.is_correct
}
function formatAnswer(v: AnswerValue | null | undefined) {
  if (v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length)) return '(blank)'
  return Array.isArray(v) ? v.join(', ') : v
}

onMounted(async () => {
  try {
    const { data } = await api.get<{ attempt: PracticeAttempt }>(`/api/practice/attempts/${id}`)
    attempt.value = data.attempt
  } catch (e) {
    error.value = errorMessage(e, 'Could not load results.')
  } finally {
    loading.value = false
  }
})
</script>
