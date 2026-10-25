<template>
  <div>
    <h1 class="page-title">{{ isReading ? 'Reading' : 'Listening' }}</h1>
    <TestTypeSwitch v-if="isReading" class="mt-3" @changed="loadTests" />
    <p class="text-muted mt-2">
      <template v-if="isReading">
        3 passages, {{ auth.user?.test_type === 'general' ? 'General Training' : 'Academic' }} format, 60 minutes. Auto-marked with explanations.
      </template>
      <template v-else>4 sections in British and American voices, about 30 minutes. Transcripts after you submit.</template>
    </p>

    <div class="mt-6 card p-4 text-sm flex gap-3">
      <component :is="isReading ? Lightbulb : Headphones" class="w-5 h-5 text-brand shrink-0" />
      <span v-if="isReading">Tip: skim the passage first, then read each question and scan for keywords. Don't spend more than 20 minutes per passage.</span>
      <span v-else>Use earphones and a quiet room. Read the questions before each recording starts. In the real test you hear each recording once.</span>
    </div>

    <Spinner v-if="loading" block />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>
    <div v-else class="mt-6 grid sm:grid-cols-2 gap-4">
      <div v-for="t in filtered" :key="t.id" class="card p-5 flex flex-col">
        <div class="flex items-center gap-2 text-xs">
          <span v-if="isReading" class="chip bg-brand-50 text-brand-dark">{{ t.test_type === 'general' ? 'General' : t.test_type === 'both' ? 'All' : 'Academic' }}</span>
          <span class="text-muted">{{ t.question_count }} questions · {{ t.duration_minutes }} min</span>
        </div>
        <h3 class="font-semibold mt-3 flex-1">{{ t.title }}</h3>
        <div class="mt-4 flex items-center justify-between gap-3">
          <div class="text-sm text-muted">
            <template v-if="t.attempts_count">Best <BandBadge :value="t.best_band" size="sm" class="ml-1" /></template>
            <template v-else>Not attempted</template>
          </div>
          <button class="btn btn-primary btn-sm" :disabled="starting === t.id" @click="start(t.id)">
            {{ starting === t.id ? 'Starting…' : t.attempts_count ? 'Retake' : 'Start' }}
          </button>
        </div>
      </div>
      <p v-if="!filtered.length" class="text-muted">No tests available yet. Check back soon.</p>
    </div>
    <Alert v-if="startError" tone="error" class="mt-4">{{ startError }}</Alert>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Headphones, Lightbulb } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import TestTypeSwitch from '@/components/TestTypeSwitch.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage, isLimitError } from '@/lib/api'
import type { PracticeAttempt, PracticeModule, PracticeTestSummary } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ module: PracticeModule }>()
const auth = useAuthStore()
const router = useRouter()

const isReading = computed(() => props.module === 'reading')
const tests = ref<PracticeTestSummary[]>([])
const loading = ref(true)
const error = ref('')
const starting = ref<number | null>(null)
const startError = ref('')

const filtered = computed(() => {
  if (!isReading.value) return tests.value
  const type = auth.user?.test_type ?? 'academic'
  return tests.value.filter((t) => t.test_type === 'both' || t.test_type === type)
})

async function loadTests() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ data: PracticeTestSummary[] }>(`/api/practice/${props.module}/tests`)
    tests.value = data.data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadTests)

async function start(testId: number) {
  starting.value = testId
  startError.value = ''
  try {
    const { data } = await api.post<{ attempt: PracticeAttempt }>(`/api/practice/${props.module}/tests/${testId}/attempts`, {})
    router.push(`/${props.module}/test/${data.attempt.id}`)
  } catch (e) {
    if (!isLimitError(e)) startError.value = errorMessage(e)
  } finally {
    starting.value = null
  }
}
</script>
