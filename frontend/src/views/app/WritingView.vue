<template>
  <div>
    <h1 class="page-title">Writing</h1>
    <p class="text-muted mt-1">
      Timed tasks marked on the four writing criteria.
      <span v-if="auth.user">Showing <strong>{{ auth.user.test_type === 'general' ? 'General Training' : 'Academic' }}</strong> tasks,
        <RouterLink to="/settings" class="text-brand-dark font-semibold">change</RouterLink>.</span>
    </p>

    <div class="mt-6 inline-flex rounded-xl bg-gray-100 p-1">
      <button
        v-for="n in [1, 2] as const"
        :key="n"
        class="px-4 h-10 rounded-lg text-sm font-semibold transition"
        :class="taskNumber === n ? 'bg-white shadow text-ink' : 'text-muted'"
        @click="taskNumber = n"
      >
        Task {{ n }}
      </button>
    </div>
    <p class="text-sm text-muted mt-3">
      {{
        taskNumber === 1
          ? auth.user?.test_type === 'general'
            ? 'Write a letter of at least 150 words in about 20 minutes.'
            : 'Describe a chart, table or diagram in at least 150 words in about 20 minutes.'
          : 'Write an essay of at least 250 words in about 40 minutes. Task 2 counts double.'
      }}
    </p>

    <Spinner v-if="loading" block />
    <Alert v-else-if="error" tone="error" class="mt-4">{{ error }}</Alert>
    <div v-else class="mt-4 grid sm:grid-cols-2 gap-4">
      <RouterLink v-for="t in filtered" :key="t.id" :to="`/writing/task/${t.id}`" class="card p-5 hover:border-brand/40 transition flex flex-col">
        <div class="flex items-center gap-2 text-xs">
          <span class="chip bg-brand-50 text-brand-dark">Task {{ t.task_number }}</span>
          <span v-if="t.visual" class="chip bg-gray-100 text-muted">{{ t.visual.type === 'table' ? 'Table' : `${t.visual.type} chart` }}</span>
          <span class="text-muted ml-auto">{{ t.recommended_minutes }} min</span>
        </div>
        <h3 class="font-semibold mt-3">{{ t.title }}</h3>
        <p class="text-sm text-muted mt-1 line-clamp-3">{{ t.prompt }}</p>
      </RouterLink>
      <p v-if="!filtered.length" class="text-muted">No tasks available yet. Check back soon.</p>
    </div>

    <h2 class="section-title mt-10">Your writing history</h2>
    <div v-if="history.length" class="mt-3 card divide-y divide-gray-100">
      <RouterLink v-for="a in history" :key="a.id" :to="`/writing/report/${a.id}`" class="flex items-center gap-3 p-4 hover:bg-gray-50">
        <div class="flex-1 min-w-0">
          <div class="font-medium truncate">{{ a.task?.title ?? 'Writing task' }}</div>
          <div class="text-xs text-muted">Task {{ a.task?.task_number }} · {{ a.word_count }} words · {{ dateTime(a.created_at) }}</div>
        </div>
        <span v-if="a.status === 'processing'" class="chip bg-accent/15 text-[#b4730a]">Marking…</span>
        <span v-else-if="a.status === 'failed'" class="chip bg-bad/10 text-bad">Failed</span>
        <BandBadge v-else :value="a.overall_band" size="sm" />
      </RouterLink>
    </div>
    <p v-else-if="!loading" class="mt-3 text-muted">No essays yet.</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage } from '@/lib/api'
import { dateTime } from '@/lib/format'
import type { Paginated, WritingAttempt, WritingTask } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const taskNumber = ref<1 | 2>(1)
const tasks = ref<WritingTask[]>([])
const history = ref<WritingAttempt[]>([])
const loading = ref(true)
const error = ref('')

const filtered = computed(() => {
  const type = auth.user?.test_type ?? 'academic'
  return tasks.value.filter((t) => t.test_type === 'both' || t.test_type === type)
})

async function loadTasks() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ data: WritingTask[] }>('/api/writing/tasks', { params: { task_number: taskNumber.value } })
    tasks.value = data.data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

watch(taskNumber, loadTasks)

onMounted(async () => {
  loadTasks()
  try {
    const { data } = await api.get<Paginated<WritingAttempt>>('/api/writing/attempts')
    history.value = data.data
  } catch {
    /* non-critical */
  }
})
</script>
