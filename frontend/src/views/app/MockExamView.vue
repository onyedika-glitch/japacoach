<template>
  <div>
    <RouterLink to="/mock" class="text-sm text-muted hover:text-ink inline-flex items-center gap-1"><ArrowLeft class="w-4 h-4" /> Mock exams</RouterLink>

    <Spinner v-if="loading" block label="Loading mock exam…" />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <template v-else-if="exam">
      <div class="mt-4 card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6" :class="exam.status === 'completed' ? 'bg-ink text-white border-none' : ''">
        <div class="flex-1">
          <h1 class="font-grotesk text-2xl font-bold">Mock exam · {{ exam.test_type === 'general' ? 'General Training' : 'Academic' }}</h1>
          <p class="text-sm mt-1" :class="exam.status === 'completed' ? 'text-gray-300' : 'text-muted'">
            {{ exam.status === 'completed' ? 'Completed — here is your overall estimate.' : `Started ${dateTime(exam.created_at)}. Do the parts in order.` }}
          </p>
        </div>
        <div v-if="exam.status === 'completed'" class="text-center">
          <div class="text-sm text-gray-300">Overall band</div>
          <div class="font-grotesk text-6xl font-bold">{{ band(exam.overall_band) }}</div>
        </div>
      </div>

      <ol class="mt-6 space-y-3">
        <li v-for="(step, i) in steps" :key="step.key" class="card p-5 flex flex-col sm:flex-row sm:items-center gap-4" :class="step.locked ? 'opacity-60' : ''">
          <div class="flex items-center gap-4 flex-1">
            <div
              class="w-11 h-11 rounded-xl grid place-items-center shrink-0 font-bold"
              :class="step.status === 'done' ? 'bg-good/10 text-good' : 'bg-brand-50 text-brand-dark'"
            >
              <CheckCircle2 v-if="step.status === 'done'" class="w-6 h-6" />
              <span v-else>{{ i + 1 }}</span>
            </div>
            <div>
              <div class="font-semibold flex items-center gap-2">
                {{ step.label }}
                <span v-if="step.status === 'processing'" class="chip bg-accent/15 text-[#b4730a]">Marking…</span>
                <span v-else-if="step.status === 'in_progress'" class="chip bg-gray-100 text-muted">In progress</span>
              </div>
              <div class="text-sm text-muted">{{ step.detail }}</div>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <BandBadge v-if="step.status === 'done'" :value="step.band" size="sm" />
            <button
              v-if="step.action"
              class="btn btn-sm"
              :class="step.primary ? 'btn-primary' : 'btn-secondary'"
              :disabled="step.locked || busy === step.key"
              @click="step.action()"
            >
              {{ busy === step.key ? 'Opening…' : step.actionLabel }}
            </button>
          </div>
        </li>
      </ol>
      <Alert v-if="actionError" tone="error" class="mt-4">{{ actionError }}</Alert>
      <p class="text-xs text-muted mt-6">Overall band is the average of the four module bands, rounded the way IELTS rounds (to the nearest half band).</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, CheckCircle2 } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage, isLimitError } from '@/lib/api'
import { band, dateTime } from '@/lib/format'
import { usePolling } from '@/composables/usePolling'
import type { MockExam, PartStatus, PracticeAttempt, PracticeModule, SpeakingAttempt } from '@/lib/types'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)
const exam = ref<MockExam | null>(null)
const loading = ref(true)
const error = ref('')
const busy = ref<string | null>(null)
const actionError = ref('')

async function load() {
  const { data } = await api.get<{ exam: MockExam }>(`/api/mock-exams/${id}`)
  exam.value = data.exam
}

const anyProcessing = () => {
  const p = exam.value?.parts
  return !!p && [p.listening.status, p.reading.status, p.writing.status, p.speaking.status].includes('processing')
}
const polling = usePolling(load, anyProcessing, 6000)

onMounted(async () => {
  try {
    await load()
    polling.start()
  } catch (e) {
    error.value = errorMessage(e, 'Could not load this mock exam.')
  } finally {
    loading.value = false
  }
})

function finished(s: PartStatus) {
  return s === 'done' || s === 'processing'
}

async function run(key: string, fn: () => Promise<void>) {
  busy.value = key
  actionError.value = ''
  try {
    await fn()
  } catch (e) {
    if (!isLimitError(e)) actionError.value = errorMessage(e)
  } finally {
    busy.value = null
  }
}

function practiceAction(module: PracticeModule) {
  const part = exam.value!.parts[module]
  if (part.status === 'done' && part.attempt_id) return () => router.push(`/practice/result/${part.attempt_id}`)
  if (part.attempt_id) return () => router.push({ path: `/${module}/test/${part.attempt_id}`, query: { mock: String(id) } })
  return () =>
    run(module, async () => {
      const { data } = await api.post<{ attempt: PracticeAttempt }>(`/api/practice/${module}/tests/${part.test_id}/attempts`, { mock_exam_id: id })
      router.push({ path: `/${module}/test/${data.attempt.id}`, query: { mock: String(id) } })
    })
}

const steps = computed(() => {
  const e = exam.value
  if (!e) return []
  const p = e.parts
  const listeningDone = finished(p.listening.status)
  const readingDone = finished(p.reading.status)
  const writingDone = finished(p.writing.status)

  const w = p.writing
  let writingAction: (() => void) | null
  let writingLabel = 'Start'
  if (!w.task1_attempt_id) {
    writingAction = () => router.push({ path: `/writing/task/${w.task1_id}`, query: { mock: String(id) } })
    writingLabel = 'Start Task 1'
  } else if (!w.task2_attempt_id) {
    writingAction = () => router.push({ path: `/writing/task/${w.task2_id}`, query: { mock: String(id) } })
    writingLabel = 'Start Task 2'
  } else {
    writingAction = () => router.push(`/writing/report/${w.task2_attempt_id}`)
    writingLabel = 'View Task 2'
  }

  const s = p.speaking
  let speakingAction: () => void
  let speakingLabel = 'Start'
  if (!s.attempt_id) {
    speakingAction = () =>
      run('speaking', async () => {
        const { data } = await api.post<{ attempt: SpeakingAttempt }>('/api/speaking/attempts', { mode: 'full', mock_exam_id: id })
        router.push({ path: `/speaking/test/${data.attempt.id}`, query: { mock: String(id) } })
      })
  } else if (s.status === 'in_progress') {
    speakingAction = () => router.push({ path: `/speaking/test/${s.attempt_id}`, query: { mock: String(id) } })
    speakingLabel = 'Continue'
  } else {
    speakingAction = () => router.push(`/speaking/report/${s.attempt_id}`)
    speakingLabel = 'View report'
  }

  const label = (status: PartStatus, attemptId: number | null) =>
    status === 'done' || status === 'processing' ? 'View results' : attemptId ? 'Continue' : 'Start'

  return [
    {
      key: 'listening',
      label: 'Listening',
      detail: '4 parts · 30 minutes · each recording plays once',
      status: p.listening.status,
      band: p.listening.band,
      locked: false,
      action: practiceAction('listening'),
      actionLabel: label(p.listening.status, p.listening.attempt_id),
      primary: !listeningDone,
    },
    {
      key: 'reading',
      label: 'Reading',
      detail: '3 passages · 60 minutes',
      status: p.reading.status,
      band: p.reading.band,
      locked: !listeningDone,
      action: practiceAction('reading'),
      actionLabel: label(p.reading.status, p.reading.attempt_id),
      primary: listeningDone && !readingDone,
    },
    {
      key: 'writing',
      label: 'Writing',
      detail: `Task 1 (20 min) and Task 2 (40 min)${w.task1_attempt_id && !w.task2_attempt_id ? ' · Task 1 submitted' : ''}`,
      status: p.writing.status,
      band: p.writing.band,
      locked: !readingDone,
      action: writingAction,
      actionLabel: writingLabel,
      primary: readingDone && !writingDone,
    },
    {
      key: 'speaking',
      label: 'Speaking',
      detail: 'All 3 parts · 11–14 minutes',
      status: p.speaking.status,
      band: p.speaking.band,
      locked: !writingDone,
      action: speakingAction,
      actionLabel: speakingLabel,
      primary: writingDone && !finished(p.speaking.status),
    },
  ]
})
</script>
