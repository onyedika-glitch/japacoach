<template>
  <div class="max-w-2xl mx-auto px-4 py-6 sm:py-10">
    <Spinner v-if="loading" block label="Preparing your test…" />
    <Alert v-else-if="loadError" tone="error">{{ loadError }}</Alert>

    <!-- MIC CHECK -->
    <div v-else-if="stage === 'check'" class="card p-6 sm:p-8 text-center">
      <div class="mx-auto w-16 h-16 rounded-full bg-brand-50 text-brand grid place-items-center">
        <Mic class="w-8 h-8" />
      </div>
      <h1 class="font-grotesk text-2xl font-bold mt-4">Microphone check</h1>
      <p class="text-muted mt-2">
        The examiner will ask {{ questions.length }} question{{ questions.length === 1 ? '' : 's' }}. Recording starts automatically after each
        question. Speak naturally, then tap <strong>Done</strong>.
      </p>

      <template v-if="rec.permission.value === 'granted'">
        <p class="mt-6 text-sm font-medium">Say something. The bar should move.</p>
        <div class="mt-2 h-3 rounded-full bg-gray-100 overflow-hidden max-w-xs mx-auto">
          <div class="h-full bg-brand transition-[width] duration-75" :style="{ width: Math.round(rec.level.value * 100) + '%' }" />
        </div>
        <button class="btn btn-primary w-full mt-8 h-14 text-lg" @click="beginTest">
          {{ resumeIndex > 0 ? 'Continue test' : 'Start test' }}
        </button>
      </template>
      <template v-else>
        <Alert v-if="rec.permission.value === 'denied'" tone="error" class="mt-6 text-left">
          Microphone access was blocked. Allow the microphone for this site in your browser settings, then try again.
        </Alert>
        <Alert v-else-if="rec.permission.value === 'unsupported'" tone="error" class="mt-6 text-left">
          Your browser can't record audio. Please use an up-to-date Chrome, Safari or Firefox.
        </Alert>
        <button class="btn btn-primary w-full mt-8 h-14 text-lg" @click="rec.requestMic()">Allow microphone</button>
      </template>
    </div>

    <!-- RUNNING -->
    <div v-else-if="stage === 'running' && current">
      <div class="flex items-center justify-between text-sm text-muted">
        <span class="font-semibold text-ink">Part {{ current.part }}</span>
        <span>Question {{ index + 1 }} of {{ questions.length }}</span>
      </div>
      <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-full bg-brand transition-all" :style="{ width: ((index + (phase === 'done' ? 1 : 0)) / questions.length) * 100 + '%' }" />
      </div>

      <!-- Part intro -->
      <div v-if="phase === 'intro'" class="card p-6 sm:p-8 mt-6 text-center">
        <div class="font-grotesk text-sm font-semibold text-brand-dark uppercase tracking-wider">Part {{ current.part }}</div>
        <h2 class="font-grotesk text-2xl font-bold mt-2">{{ partInfo[current.part].title }}</h2>
        <p class="text-muted mt-2">{{ partInfo[current.part].text }}</p>
        <button class="btn btn-primary mt-6 w-full sm:w-auto px-10" @click="askQuestion">Begin Part {{ current.part }}</button>
      </div>

      <template v-else>
        <div class="card p-6 sm:p-8 mt-6">
          <div class="flex items-center gap-2 text-sm text-muted">
            <Volume2 class="w-4 h-4" :class="phase === 'listening' ? 'text-brand animate-pulse' : ''" />
            {{ phase === 'listening' ? 'Examiner is speaking…' : 'Examiner' }}
          </div>
          <p class="mt-3 text-xl sm:text-2xl font-semibold leading-snug">{{ current.prompt }}</p>

          <div v-if="current.part === 2 && current.cue_points?.length" class="mt-5 rounded-xl bg-brand-50 p-4">
            <div class="text-sm font-semibold">You should say:</div>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-[15px]">
              <li v-for="p in current.cue_points" :key="p">{{ p }}</li>
            </ul>
          </div>

          <button v-if="phase === 'listening' && playBlocked" class="btn btn-secondary btn-sm mt-4" @click="askQuestion">
            <Play class="w-4 h-4" /> Play question
          </button>
        </div>

        <!-- Prep timer (Part 2) -->
        <div v-if="phase === 'prep'" class="card p-6 mt-4 text-center">
          <div class="text-sm text-muted">Preparation time: make notes if you like</div>
          <div class="font-grotesk text-5xl font-bold mt-2 tabular-nums">{{ clock(prep.remaining.value) }}</div>
          <textarea v-model="notes" class="input h-28 py-3 mt-4 text-sm" placeholder="Your notes (not marked)" />
          <button class="btn btn-secondary mt-4" @click="startRecording">I'm ready to speak</button>
        </div>

        <!-- Recording -->
        <div v-if="phase === 'recording'" class="card p-6 mt-4 text-center">
          <div class="flex items-center justify-center gap-2 text-bad font-semibold">
            <span class="w-3 h-3 rounded-full bg-bad animate-pulse" /> Recording
          </div>
          <div class="font-grotesk text-5xl font-bold mt-2 tabular-nums">{{ clock(rec.elapsed.value) }}</div>
          <div class="text-sm text-muted">up to {{ clock(maxSeconds) }}</div>
          <div class="mt-4 h-2 rounded-full bg-gray-100 overflow-hidden max-w-xs mx-auto">
            <div class="h-full bg-brand transition-[width] duration-75" :style="{ width: Math.round(rec.level.value * 100) + '%' }" />
          </div>
          <button class="btn btn-primary w-full mt-6 h-14 text-lg" :disabled="rec.elapsed.value < 2" @click="finishAnswer">
            <Check class="w-5 h-5" /> Done
          </button>
          <p v-if="current.part === 2 && rec.elapsed.value < 60" class="text-xs text-muted mt-2">Try to keep talking for 1 to 2 minutes.</p>
        </div>
      </template>

      <UploadStatus :uploads="uploads" :questions="questions" class="mt-6" @retry="retryUpload" />
    </div>

    <!-- FINISHING -->
    <div v-else-if="stage === 'finishing'" class="card p-6 sm:p-8 text-center">
      <template v-if="failedUploads.length">
        <AlertTriangle class="w-10 h-10 mx-auto text-accent" />
        <h2 class="font-grotesk text-xl font-bold mt-3">Some answers didn't upload</h2>
        <p class="text-muted mt-2">Check your connection and retry. Your recordings are still on this device.</p>
        <UploadStatus :uploads="uploads" :questions="questions" class="mt-4 text-left" @retry="retryUpload" />
      </template>
      <template v-else>
        <Spinner :label="submitError ? '' : 'Sending your answers to the examiner…'" />
        <Alert v-if="submitError" tone="error" class="mt-4 text-left">{{ submitError }}</Alert>
        <button v-if="submitError" class="btn btn-primary mt-4" @click="submit">Try again</button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onBeforeUnmount, onMounted, reactive, ref, type PropType } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertTriangle, Check, CheckCircle2, CloudUpload, Mic, Play, RotateCcw, Volume2 } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'
import { clock } from '@/lib/format'
import { extensionFor, useRecorder, type Recording } from '@/composables/useRecorder'
import { useCountdown } from '@/composables/useCountdown'
import type { SpeakingAttempt, SpeakingQuestion } from '@/lib/types'

type Upload = { status: 'uploading' | 'done' | 'error'; recording?: Recording; error?: string }

const route = useRoute()
const router = useRouter()
const attemptId = Number(route.params.id)
const mockId = typeof route.query.mock === 'string' ? route.query.mock : null

const loading = ref(true)
const loadError = ref('')
const questions = ref<SpeakingQuestion[]>([])
const stage = ref<'check' | 'running' | 'finishing'>('check')
const phase = ref<'intro' | 'listening' | 'prep' | 'recording' | 'done'>('intro')
const index = ref(0)
const resumeIndex = ref(0)
const playBlocked = ref(false)
const notes = ref('')
const submitError = ref('')
const uploads = reactive<Record<number, Upload>>({})

const rec = useRecorder()
const prep = useCountdown({ stopAtZero: true, onZero: () => phase.value === 'prep' && startRecording() })
let maxTimer: ReturnType<typeof setTimeout> | null = null
let audio: HTMLAudioElement | null = null
let speechFallbackTimer: ReturnType<typeof setTimeout> | null = null

const current = computed(() => questions.value[index.value])
const maxSeconds = computed(() => (current.value?.part === 2 ? 120 : 60))
const failedUploads = computed(() => Object.entries(uploads).filter(([, u]) => u.status === 'error'))

const partInfo: Record<number, { title: string; text: string }> = {
  1: { title: 'Introduction and interview', text: 'Short questions about familiar topics. Aim for 2 to 4 sentences per answer.' },
  2: { title: 'Individual long turn', text: "You'll get a cue card and 1 minute to prepare. Then speak for 1 to 2 minutes." },
  3: { title: 'Two-way discussion', text: 'Deeper questions linked to Part 2. Give opinions, reasons and examples.' },
}

onMounted(async () => {
  try {
    const { data } = await api.get<{ attempt: SpeakingAttempt }>(`/api/speaking/attempts/${attemptId}`)
    if (data.attempt.status !== 'in_progress') {
      router.replace(`/speaking/report/${attemptId}`)
      return
    }
    questions.value = [...(data.attempt.questions ?? [])].sort((a, b) => a.part - b.part || a.position - b.position)
    questions.value.forEach((q) => {
      if (q.answer && q.answer.status !== 'failed') uploads[q.id] = { status: 'done' }
    })
    const firstUnanswered = questions.value.findIndex((q) => !uploads[q.id])
    resumeIndex.value = firstUnanswered === -1 ? questions.value.length : firstUnanswered
    if (resumeIndex.value >= questions.value.length && questions.value.length) {
      stage.value = 'finishing'
      submit()
    }
  } catch (e) {
    loadError.value = errorMessage(e, 'Could not load this test.')
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  stopAudio()
  if (maxTimer) clearTimeout(maxTimer)
  window.removeEventListener('beforeunload', warnUnload)
})

function warnUnload(e: BeforeUnloadEvent) {
  if (stage.value === 'running' || Object.values(uploads).some((u) => u.status === 'uploading')) {
    e.preventDefault()
  }
}

function beginTest() {
  window.addEventListener('beforeunload', warnUnload)
  stage.value = 'running'
  index.value = resumeIndex.value
  enterQuestion()
}

function isFirstOfPart(i: number) {
  return i === 0 || questions.value[i - 1].part !== questions.value[i].part
}

function enterQuestion() {
  notes.value = ''
  phase.value = isFirstOfPart(index.value) || index.value === resumeIndex.value ? 'intro' : 'listening'
  if (phase.value === 'listening') askQuestion()
}

function stopAudio() {
  if (audio) {
    audio.onended = null
    audio.pause()
    audio = null
  }
  if (speechFallbackTimer) clearTimeout(speechFallbackTimer)
  if ('speechSynthesis' in window) window.speechSynthesis.cancel()
}

function afterQuestionSpoken() {
  stopAudio()
  if (phase.value !== 'listening') return
  if (current.value.part === 2) {
    phase.value = 'prep'
    prep.start(60)
  } else {
    startRecording()
  }
}

function askQuestion() {
  phase.value = 'listening'
  playBlocked.value = false
  stopAudio()
  const q = current.value
  if (q.audio_url) {
    audio = new Audio(q.audio_url)
    audio.onended = afterQuestionSpoken
    audio.onerror = () => speak(q)
    audio.play().catch(() => {
      playBlocked.value = true
    })
  } else {
    speak(q)
  }
}

function speak(q: SpeakingQuestion) {
  if (!('speechSynthesis' in window)) {
    afterQuestionSpoken()
    return
  }
  const text = q.part === 2 ? `${q.prompt}. ${(q.cue_points ?? []).length ? 'You should say: ' + q.cue_points!.join('. ') : ''}` : q.prompt
  const u = new SpeechSynthesisUtterance(text)
  const voices = window.speechSynthesis.getVoices()
  u.voice = voices.find((v) => v.lang === 'en-GB') ?? voices.find((v) => v.lang.startsWith('en')) ?? null
  u.lang = 'en-GB'
  u.rate = 0.95
  u.onend = afterQuestionSpoken
  window.speechSynthesis.cancel()
  window.speechSynthesis.speak(u)
  // Some mobile browsers never fire onend, so fall back to an estimate.
  speechFallbackTimer = setTimeout(afterQuestionSpoken, Math.max(4000, text.split(' ').length * 450 + 1500))
}

function startRecording() {
  prep.stop()
  phase.value = 'recording'
  try {
    rec.start()
  } catch {
    stage.value = 'check'
    return
  }
  if (maxTimer) clearTimeout(maxTimer)
  maxTimer = setTimeout(() => {
    if (phase.value === 'recording') finishAnswer()
  }, maxSeconds.value * 1000)
}

async function finishAnswer() {
  if (maxTimer) clearTimeout(maxTimer)
  const q = current.value
  const recording = await rec.stop()
  phase.value = 'done'
  upload(q.id, recording)

  if (index.value < questions.value.length - 1) {
    index.value++
    enterQuestion()
  } else {
    stage.value = 'finishing'
    window.removeEventListener('beforeunload', warnUnload)
    await waitForUploads()
    if (!failedUploads.value.length) submit()
  }
}

async function upload(questionId: number, recording: Recording) {
  uploads[questionId] = { status: 'uploading', recording }
  const form = new FormData()
  form.append('question_id', String(questionId))
  form.append('duration_seconds', String(recording.durationSeconds))
  form.append('audio', recording.blob, `answer-${questionId}.${extensionFor(recording.mimeType)}`)
  try {
    await api.post(`/api/speaking/attempts/${attemptId}/answers`, form, { timeout: 120000 })
    uploads[questionId] = { status: 'done' }
  } catch (e) {
    uploads[questionId] = { status: 'error', recording, error: errorMessage(e, 'Upload failed') }
  }
}

async function retryUpload(questionId: number) {
  const u = uploads[questionId]
  if (!u?.recording) return
  await upload(questionId, u.recording)
  if (stage.value === 'finishing' && !failedUploads.value.length) {
    await waitForUploads()
    submit()
  }
}

function waitForUploads(): Promise<void> {
  return new Promise((resolve) => {
    const check = () => (Object.values(uploads).some((u) => u.status === 'uploading') ? setTimeout(check, 300) : resolve())
    check()
  })
}

async function submit() {
  submitError.value = ''
  try {
    await api.post(`/api/speaking/attempts/${attemptId}/submit`)
    rec.release()
    router.replace(mockId ? `/mock/${mockId}` : `/speaking/report/${attemptId}`)
  } catch (e) {
    submitError.value = errorMessage(e, 'Could not submit your test.')
  }
}

/** Small inline component listing upload progress per answered question. */
const UploadStatus = defineComponent({
  props: {
    uploads: { type: Object as PropType<Record<number, Upload>>, required: true },
    questions: { type: Array as PropType<SpeakingQuestion[]>, required: true },
  },
  emits: ['retry'],
  setup(props, { emit }) {
    return () => {
      const items = props.questions.filter((q) => props.uploads[q.id] && props.uploads[q.id].status !== 'done')
      if (!items.length) return null
      return h(
        'div',
        { class: 'space-y-2' },
        items.map((q) => {
          const u = props.uploads[q.id]
          const n = props.questions.indexOf(q) + 1
          return h('div', { class: 'flex items-center gap-2 text-sm rounded-xl bg-white border border-gray-100 px-3 py-2' }, [
            u.status === 'uploading'
              ? h(CloudUpload, { class: 'w-4 h-4 text-brand animate-pulse' })
              : u.status === 'done'
                ? h(CheckCircle2, { class: 'w-4 h-4 text-good' })
                : h(AlertTriangle, { class: 'w-4 h-4 text-bad' }),
            h('span', { class: 'flex-1' }, u.status === 'uploading' ? `Uploading answer ${n}…` : `Answer ${n}: ${u.error ?? 'failed'}`),
            u.status === 'error'
              ? h('button', { class: 'btn btn-secondary btn-sm', onClick: () => emit('retry', q.id) }, [h(RotateCcw, { class: 'w-4 h-4' }), 'Retry'])
              : null,
          ])
        }),
      )
    }
  },
})
</script>
