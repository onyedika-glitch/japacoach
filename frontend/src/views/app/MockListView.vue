<template>
  <div>
    <h1 class="page-title">Full mock exam</h1>
    <p class="text-muted mt-1">The closest thing to test day: Listening → Reading → Writing → Speaking, with an overall band at the end.</p>

    <div class="mt-6 card p-6 sm:p-8 bg-brand-50 border-brand/20">
      <div class="grid sm:grid-cols-4 gap-4 text-sm">
        <div v-for="p in parts" :key="p.label" class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-white grid place-items-center text-brand"><component :is="p.icon" class="w-5 h-5" /></div>
          <div>
            <div class="font-semibold">{{ p.label }}</div>
            <div class="text-muted text-xs">{{ p.time }}</div>
          </div>
        </div>
      </div>
      <p class="text-sm text-muted mt-5">
        About 2 hours 45 minutes in total. You can take a break between parts, and your progress is saved. A mock uses one full speaking mock, two
        writing evaluations and one reading and listening test from your plan.
      </p>
      <button class="btn btn-primary mt-6 w-full sm:w-auto px-8" :disabled="creating" @click="create">
        {{ creating ? 'Setting up…' : 'Start a new mock exam' }}
      </button>
      <Alert v-if="error" tone="error" class="mt-4">{{ error }}</Alert>
    </div>

    <h2 class="section-title mt-10">Your mock exams</h2>
    <Spinner v-if="loading" block />
    <div v-else-if="exams.length" class="mt-3 card divide-y divide-gray-100">
      <RouterLink v-for="e in exams" :key="e.id" :to="`/mock/${e.id}`" class="flex items-center gap-3 p-4 hover:bg-gray-50">
        <div class="flex-1">
          <div class="font-medium">Mock exam · {{ e.test_type === 'general' ? 'General Training' : 'Academic' }}</div>
          <div class="text-xs text-muted">{{ dateTime(e.created_at) }}</div>
        </div>
        <span v-if="e.status !== 'completed'" class="chip bg-gray-100 text-muted">In progress</span>
        <BandBadge v-else :value="e.overall_band" size="sm" />
      </RouterLink>
    </div>
    <p v-else class="mt-3 text-muted">No mock exams yet.</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BookOpen, Headphones, Mic, PenLine } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage, isLimitError } from '@/lib/api'
import { dateTime } from '@/lib/format'
import type { MockExam } from '@/lib/types'

const router = useRouter()
const exams = ref<MockExam[]>([])
const loading = ref(true)
const creating = ref(false)
const error = ref('')

const parts = [
  { label: 'Listening', time: '30 min', icon: Headphones },
  { label: 'Reading', time: '60 min', icon: BookOpen },
  { label: 'Writing', time: '60 min', icon: PenLine },
  { label: 'Speaking', time: '11 to 14 min', icon: Mic },
]

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: MockExam[] }>('/api/mock-exams')
    exams.value = data.data
  } catch {
    /* non-critical */
  } finally {
    loading.value = false
  }
})

async function create() {
  creating.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ exam: MockExam }>('/api/mock-exams')
    router.push(`/mock/${data.exam.id}`)
  } catch (e) {
    if (!isLimitError(e)) error.value = errorMessage(e)
  } finally {
    creating.value = false
  }
}
</script>
