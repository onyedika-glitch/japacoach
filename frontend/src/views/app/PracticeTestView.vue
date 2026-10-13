<template>
  <div class="max-w-7xl mx-auto px-3 sm:px-4 py-4">
    <Spinner v-if="loading" block label="Loading test…" />
    <Alert v-else-if="loadError" tone="error">{{ loadError }}</Alert>

    <template v-else-if="attempt">
      <!-- Top bar -->
      <div class="sticky top-14 z-20 bg-white/95 backdrop-blur rounded-2xl border border-gray-100 px-3 sm:px-4 py-2.5 flex flex-wrap items-center gap-3">
        <div class="font-semibold truncate flex-1 min-w-0 text-sm sm:text-base">{{ attempt.test.title }}</div>
        <div
          class="flex items-center gap-1.5 font-grotesk font-bold tabular-nums"
          :class="timer.remaining.value < 300 ? 'text-bad' : ''"
          :title="'Time remaining'"
        >
          <Timer class="w-4 h-4" /> {{ clock(timer.remaining.value) }}
        </div>
        <span class="text-xs text-muted hidden sm:inline">{{ saveLabel }}</span>
        <button class="btn btn-primary btn-sm" :disabled="submitting" @click="confirmSubmit">{{ submitting ? 'Submitting…' : 'Submit' }}</button>
      </div>

      <!-- Section tabs -->
      <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="(s, i) in sections"
          :key="s.id"
          class="shrink-0 px-4 h-10 rounded-xl text-sm font-semibold border transition"
          :class="i === sectionIndex ? 'bg-ink text-white border-ink' : 'bg-white border-gray-200 text-muted'"
          @click="goSection(i)"
        >
          {{ isReading ? 'Passage' : 'Part' }} {{ i + 1 }}
          <span class="ml-1 text-xs opacity-70">{{ answeredIn(s) }}/{{ s.questions.length }}</span>
        </button>
      </div>

      <!-- Mobile pane switch (reading) -->
      <div v-if="isReading" class="lg:hidden mt-3 grid grid-cols-2 rounded-xl bg-gray-100 p-1">
        <button class="h-9 rounded-lg text-sm font-semibold" :class="pane === 'passage' ? 'bg-white shadow' : 'text-muted'" @click="pane = 'passage'">Passage</button>
        <button class="h-9 rounded-lg text-sm font-semibold" :class="pane === 'questions' ? 'bg-white shadow' : 'text-muted'" @click="pane = 'questions'">
          Questions
        </button>
      </div>

      <div v-if="section" class="mt-3 grid gap-4" :class="isReading ? 'lg:grid-cols-2' : ''">
        <!-- Reading passage -->
        <article
          v-if="isReading"
          class="card p-5 sm:p-6 lg:max-h-[calc(100vh-11rem)] lg:overflow-y-auto"
          :class="pane === 'passage' ? '' : 'hidden lg:block'"
        >
          <h2 class="font-grotesk text-xl font-bold">{{ section.title }}</h2>
          <p v-for="(para, i) in paragraphs" :key="i" class="mt-4 text-[16px] leading-relaxed">{{ para }}</p>
        </article>

        <!-- Listening audio -->
        <div v-else class="card p-5">
          <h2 class="font-grotesk text-lg font-bold">{{ section.title }}</h2>
          <div class="mt-3 flex items-center gap-3">
            <button
              class="w-12 h-12 rounded-full bg-brand text-white grid place-items-center shrink-0 disabled:opacity-40"
              :disabled="!section.audio_url || (!practiceMode && playedOnce[section.id] && !playing)"
              :aria-label="playing ? 'Pause' : 'Play'"
              @click="togglePlay"
            >
              <Pause v-if="playing" class="w-5 h-5" />
              <Play v-else class="w-5 h-5 ml-0.5" />
            </button>
            <div class="flex-1">
              <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full bg-brand" :style="{ width: progressPct + '%' }" />
              </div>
              <div class="mt-1 text-xs text-muted flex justify-between tabular-nums">
                <span>{{ clock(audioTime) }}</span><span>{{ clock(audioDuration) }}</span>
              </div>
            </div>
          </div>
          <p v-if="!section.audio_url" class="text-sm text-bad mt-2">Audio for this part isn't available yet.</p>
          <p v-else-if="!practiceMode && playedOnce[section.id] && !playing" class="text-xs text-muted mt-2">
            Recording finished. In test mode you hear each part once.
          </p>
          <label class="mt-3 flex items-center gap-2 text-sm text-muted">
            <input v-model="practiceMode" type="checkbox" class="accent-brand w-4 h-4" />
            Practice mode (allow pause and replay)
          </label>
          <audio ref="audioEl" :src="section.audio_url ?? undefined" preload="metadata" class="hidden" @timeupdate="onTime" @loadedmetadata="onTime" @ended="onEnded" @pause="playing = false" @play="playing = true" />
        </div>

        <!-- Questions -->
        <div class="card p-5 sm:p-6 lg:max-h-[calc(100vh-11rem)] lg:overflow-y-auto" :class="isReading && pane !== 'questions' ? 'hidden lg:block' : ''">
          <p v-if="section.instructions" class="text-sm text-muted bg-gray-50 rounded-xl p-3 whitespace-pre-line">{{ section.instructions }}</p>
          <div class="mt-4 space-y-6">
            <QuestionInput
              v-for="q in section.questions"
              :key="q.id"
              :question="q"
              :model-value="answers[q.id]"
              @update:model-value="setAnswer(q.id, $event)"
            />
          </div>
          <div class="mt-8 flex justify-between gap-3">
            <button class="btn btn-secondary btn-sm" :disabled="sectionIndex === 0" @click="goSection(sectionIndex - 1)"><ChevronLeft class="w-4 h-4" /> Previous</button>
            <button v-if="sectionIndex < sections.length - 1" class="btn btn-primary btn-sm" @click="goSection(sectionIndex + 1)">
              Next <ChevronRight class="w-4 h-4" />
            </button>
            <button v-else class="btn btn-primary btn-sm" :disabled="submitting" @click="confirmSubmit">Submit test</button>
          </div>
        </div>
      </div>

      <!-- Question navigator -->
      <div class="mt-4 card p-4">
        <div class="text-xs text-muted mb-2">Questions — {{ answeredTotal }} of {{ totalQuestions }} answered</div>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="q in allQuestions"
            :key="q.id"
            class="w-9 h-9 rounded-lg text-sm font-semibold border"
            :class="isAnswered(q.id) ? 'bg-brand text-white border-brand' : 'bg-white border-gray-200 text-muted'"
            @click="jumpTo(q.id)"
          >
            {{ q.number }}
          </button>
        </div>
      </div>
      <Alert v-if="submitError" tone="error" class="mt-4">{{ submitError }}</Alert>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Pause, Play, Timer } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import QuestionInput from '@/components/QuestionInput.vue'
import { api, errorMessage } from '@/lib/api'
import { clock } from '@/lib/format'
import { useCountdown } from '@/composables/useCountdown'
import type { AnswerValue, PracticeAttempt, PracticeModule, PracticeSection } from '@/lib/types'

const props = defineProps<{ module: PracticeModule }>()
const route = useRoute()
const router = useRouter()
const attemptId = Number(route.params.attemptId)
const mockId = typeof route.query.mock === 'string' ? route.query.mock : null

const isReading = computed(() => props.module === 'reading')
const attempt = ref<PracticeAttempt | null>(null)
const loading = ref(true)
const loadError = ref('')
const answers = reactive<Record<string, AnswerValue>>({})
const sectionIndex = ref(0)
const pane = ref<'passage' | 'questions'>('passage')
const dirty = ref(false)
const saving = ref(false)
const lastSaved = ref<Date | null>(null)
const submitting = ref(false)
const submitError = ref('')

const timer = useCountdown({ stopAtZero: true, onZero: () => submit(true) })

// Listening audio
const audioEl = ref<HTMLAudioElement | null>(null)
const playing = ref(false)
const audioTime = ref(0)
const audioDuration = ref(0)
const playedOnce = reactive<Record<number, boolean>>({})
const practiceMode = ref(!mockId)

const sections = computed(() => [...(attempt.value?.test.sections ?? [])].sort((a, b) => a.position - b.position))
const section = computed<PracticeSection | undefined>(() => sections.value[sectionIndex.value])
const allQuestions = computed(() => sections.value.flatMap((s) => s.questions))
const totalQuestions = computed(() => allQuestions.value.length)
const paragraphs = computed(() => (section.value?.body ?? '').split(/\n\s*\n/).filter((p) => p.trim()))
const progressPct = computed(() => (audioDuration.value ? (audioTime.value / audioDuration.value) * 100 : 0))

function isAnswered(id: number) {
  const v = answers[id]
  return Array.isArray(v) ? v.length > 0 : !!v && String(v).trim() !== ''
}
function answeredIn(s: PracticeSection) {
  return s.questions.filter((q) => isAnswered(q.id)).length
}
const answeredTotal = computed(() => allQuestions.value.filter((q) => isAnswered(q.id)).length)

const saveLabel = computed(() => (saving.value ? 'Saving…' : dirty.value ? 'Unsaved changes' : lastSaved.value ? 'All answers saved' : ''))

function setAnswer(id: number, value: string) {
  answers[id] = value
  dirty.value = true
}

async function save() {
  if (!dirty.value || saving.value || submitting.value) return
  saving.value = true
  dirty.value = false
  try {
    await api.patch(`/api/practice/attempts/${attemptId}`, { answers: { ...answers } })
    lastSaved.value = new Date()
  } catch {
    dirty.value = true
  } finally {
    saving.value = false
  }
}

let autosave: ReturnType<typeof setInterval> | null = null

onMounted(async () => {
  try {
    const { data } = await api.get<{ attempt: PracticeAttempt }>(`/api/practice/attempts/${attemptId}`)
    if (data.attempt.status === 'submitted') {
      router.replace(`/practice/result/${attemptId}`)
      return
    }
    attempt.value = data.attempt
    Object.assign(answers, data.attempt.answers ?? {})
    const started = new Date(data.attempt.started_at).getTime() || Date.now()
    timer.startUntil(started + data.attempt.test.duration_minutes * 60 * 1000)
    autosave = setInterval(save, 30000)
    window.addEventListener('beforeunload', onUnload)
  } catch (e) {
    loadError.value = errorMessage(e, 'Could not load this test.')
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  if (autosave) clearInterval(autosave)
  window.removeEventListener('beforeunload', onUnload)
  audioEl.value?.pause()
  void save()
})

function onUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}

function goSection(i: number) {
  audioEl.value?.pause()
  sectionIndex.value = i
  pane.value = 'passage'
  void save()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function jumpTo(questionId: number) {
  const i = sections.value.findIndex((s) => s.questions.some((q) => q.id === questionId))
  if (i !== -1 && i !== sectionIndex.value) goSection(i)
  pane.value = 'questions'
  await nextTick()
  document.getElementById(`q-${questionId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

watch(section, () => {
  audioTime.value = 0
  audioDuration.value = 0
  playing.value = false
})

function togglePlay() {
  const el = audioEl.value
  if (!el || !section.value) return
  if (playing.value) {
    if (practiceMode.value) el.pause()
    return
  }
  if (!practiceMode.value && playedOnce[section.value.id]) return
  void el.play()
}

function onTime() {
  const el = audioEl.value
  if (!el) return
  audioTime.value = el.currentTime
  audioDuration.value = Number.isFinite(el.duration) ? el.duration : 0
}

function onEnded() {
  playing.value = false
  if (section.value) playedOnce[section.value.id] = true
  if (practiceMode.value && audioEl.value) audioEl.value.currentTime = 0
}

function confirmSubmit() {
  const unanswered = totalQuestions.value - answeredTotal.value
  const msg = unanswered ? `You have ${unanswered} unanswered question${unanswered === 1 ? '' : 's'}. Submit anyway?` : 'Submit your answers for marking?'
  if (window.confirm(msg)) submit(false)
}

async function submit(auto: boolean) {
  if (submitting.value) return
  submitting.value = true
  submitError.value = ''
  timer.stop()
  try {
    await api.post(`/api/practice/attempts/${attemptId}/submit`, { answers: { ...answers } })
    dirty.value = false
    window.removeEventListener('beforeunload', onUnload)
    router.replace(mockId ? `/mock/${mockId}` : `/practice/result/${attemptId}`)
  } catch (e) {
    submitError.value = errorMessage(e, auto ? "Time's up, but we couldn't submit. Check your connection and press Submit." : 'Could not submit.')
  } finally {
    submitting.value = false
  }
}
</script>
