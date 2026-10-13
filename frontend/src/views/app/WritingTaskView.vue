<template>
  <div class="max-w-7xl mx-auto px-4 py-6">
    <Spinner v-if="loading" block label="Loading task…" />
    <Alert v-else-if="loadError" tone="error">{{ loadError }}</Alert>

    <div v-else-if="task" class="grid lg:grid-cols-2 gap-6 items-start">
      <!-- Prompt -->
      <div class="lg:sticky lg:top-20 space-y-4">
        <div class="card p-5">
          <div class="flex items-center gap-2">
            <span class="chip bg-brand-50 text-brand-dark">Writing Task {{ task.task_number }}</span>
            <span class="text-xs text-muted">Write at least {{ task.min_words }} words</span>
          </div>
          <h1 class="font-grotesk text-xl font-bold mt-3">{{ task.title }}</h1>
          <p class="mt-3 text-[15px] leading-relaxed whitespace-pre-line">{{ task.prompt }}</p>
        </div>
        <TaskVisual v-if="task.visual" :visual="task.visual" />
      </div>

      <!-- Editor -->
      <div class="card p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3 sticky top-14 lg:static bg-white z-10 py-1">
          <div class="flex items-center gap-2 font-grotesk text-lg font-bold tabular-nums" :class="timer.remaining.value < 0 ? 'text-bad' : timer.remaining.value < 300 ? 'text-accent' : ''">
            <Timer class="w-5 h-5" /> {{ clock(timer.remaining.value) }}
          </div>
          <div class="text-sm" :class="words >= task.min_words ? 'text-good font-semibold' : 'text-muted'">{{ words }} / {{ task.min_words }} words</div>
        </div>
        <p v-if="timer.remaining.value < 0" class="text-xs text-bad mt-1">Time's up in a real test. Finish your last sentence and submit.</p>

        <textarea
          v-model="essay"
          class="mt-3 w-full min-h-[55vh] rounded-xl border border-gray-200 p-4 text-[16px] leading-relaxed outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 resize-y"
          :placeholder="task.task_number === 1 ? 'Start writing your response…' : 'Introduction, body paragraphs, conclusion…'"
          spellcheck="false"
          autocorrect="off"
          autocapitalize="sentences"
        />
        <p class="text-xs text-muted mt-1">Spellcheck is off, like the real computer-delivered test. Your draft saves on this device automatically.</p>

        <Alert v-if="submitError" tone="error" class="mt-3">{{ submitError }}</Alert>
        <button class="btn btn-primary w-full mt-4 h-14 text-lg" :disabled="submitting || words < 20" @click="submit">
          {{ submitting ? 'Submitting…' : 'Submit for marking' }}
        </button>
        <p v-if="words < task.min_words && words >= 20" class="text-xs text-accent mt-2 text-center">
          Under {{ task.min_words }} words loses marks in the real test.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Timer } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import TaskVisual from '@/components/TaskVisual.vue'
import { api, errorMessage, isLimitError } from '@/lib/api'
import { clock, wordCount } from '@/lib/format'
import { useCountdown } from '@/composables/useCountdown'
import type { WritingAttempt, WritingTask } from '@/lib/types'

const route = useRoute()
const router = useRouter()
const taskId = Number(route.params.id)
const mockId = typeof route.query.mock === 'string' ? Number(route.query.mock) : null
const draftKey = `jc_draft_${taskId}${mockId ? `_m${mockId}` : ''}`
const startKey = `${draftKey}_start`

const task = ref<WritingTask | null>(null)
const essay = ref('')
const loading = ref(true)
const loadError = ref('')
const submitting = ref(false)
const submitError = ref('')
const timer = useCountdown()
let startedAt = Date.now()

const words = computed(() => wordCount(essay.value))

function storage(): Storage | null {
  try {
    return window.localStorage
  } catch {
    return null
  }
}

let saveTimer: ReturnType<typeof setTimeout> | null = null
watch(essay, (v) => {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(() => storage()?.setItem(draftKey, v), 400)
})

onMounted(async () => {
  try {
    const { data } = await api.get<{ task: WritingTask }>(`/api/writing/tasks/${taskId}`)
    task.value = data.task
    essay.value = storage()?.getItem(draftKey) ?? ''
    const savedStart = Number(storage()?.getItem(startKey))
    startedAt = savedStart > 0 ? savedStart : Date.now()
    storage()?.setItem(startKey, String(startedAt))
    timer.startUntil(startedAt + data.task.recommended_minutes * 60 * 1000)
  } catch (e) {
    loadError.value = errorMessage(e, 'Could not load this task.')
  } finally {
    loading.value = false
  }
})

async function submit() {
  if (!task.value) return
  if (words.value < task.value.min_words && !window.confirm(`You've written ${words.value} words (minimum ${task.value.min_words}). Submit anyway?`)) return
  submitting.value = true
  submitError.value = ''
  try {
    const { data } = await api.post<{ attempt: WritingAttempt }>('/api/writing/attempts', {
      writing_task_id: task.value.id,
      essay: essay.value,
      time_spent_seconds: Math.round((Date.now() - startedAt) / 1000),
      ...(mockId ? { mock_exam_id: mockId } : {}),
    })
    storage()?.removeItem(draftKey)
    storage()?.removeItem(startKey)
    router.replace(mockId ? `/mock/${mockId}` : `/writing/report/${data.attempt.id}`)
  } catch (e) {
    if (!isLimitError(e)) submitError.value = errorMessage(e, 'Could not submit. Your draft is saved, so try again.')
  } finally {
    submitting.value = false
  }
}
</script>
