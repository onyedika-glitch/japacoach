<template>
  <div>
    <h1 class="page-title">Speaking</h1>
    <p class="text-muted mt-1">Speak your answers out loud. Your examiner marks fluency, vocabulary, grammar and pronunciation.</p>

    <div class="mt-6 grid md:grid-cols-2 gap-4">
      <button
        v-for="m in modes"
        :key="m.mode"
        class="card p-5 text-left hover:border-brand/50 transition disabled:opacity-60"
        :class="m.mode === 'full' ? 'md:col-span-2 bg-brand-50 border-brand/20' : ''"
        :disabled="!!starting"
        @click="start(m.mode)"
      >
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl grid place-items-center shrink-0" :class="m.mode === 'full' ? 'bg-brand text-white' : 'bg-brand-50 text-brand-dark'">
            <component :is="m.icon" class="w-6 h-6" />
          </div>
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="font-grotesk text-lg font-semibold">{{ m.title }}</h2>
              <span class="chip bg-white border border-gray-200 text-muted">{{ m.time }}</span>
            </div>
            <p class="text-[15px] text-muted mt-1">{{ m.text }}</p>
          </div>
          <Spinner v-if="starting === m.mode" />
          <ChevronRight v-else class="w-5 h-5 text-muted mt-3" />
        </div>
      </button>
    </div>
    <Alert v-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <div class="mt-6 card p-4 text-sm flex gap-3">
      <Headphones class="w-5 h-5 text-brand shrink-0" />
      <span>Find a quiet place and use earphones with a mic if you can. You'll do a quick microphone check before the test starts.</span>
    </div>

    <h2 class="section-title mt-10">Your speaking history</h2>
    <Spinner v-if="loadingHistory" block />
    <div v-else-if="history.length" class="mt-3 card divide-y divide-gray-100">
      <RouterLink
        v-for="a in history"
        :key="a.id"
        :to="a.status === 'in_progress' ? `/speaking/test/${a.id}` : `/speaking/report/${a.id}`"
        class="flex items-center gap-3 p-4 hover:bg-gray-50"
      >
        <div class="flex-1">
          <div class="font-medium">{{ modeTitle(a.mode) }}</div>
          <div class="text-xs text-muted">{{ dateTime(a.created_at) }}</div>
        </div>
        <span v-if="a.status === 'processing'" class="chip bg-accent/15 text-[#b4730a]">Marking…</span>
        <span v-else-if="a.status === 'in_progress'" class="chip bg-gray-100 text-muted">Continue</span>
        <span v-else-if="a.status === 'failed'" class="chip bg-bad/10 text-bad">Failed</span>
        <BandBadge v-else :value="a.overall_band" size="sm" />
      </RouterLink>
    </div>
    <p v-else class="mt-3 text-muted">No speaking attempts yet — try a Part 1 drill first. It takes about 4 minutes.</p>
    <div v-if="nextPage" class="mt-4 text-center">
      <button class="btn btn-secondary btn-sm" @click="loadHistory(nextPage)">Load more</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronRight, Headphones, MessageCircle, MessagesSquare, Mic, NotebookPen } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage, isLimitError } from '@/lib/api'
import { dateTime } from '@/lib/format'
import type { Paginated, SpeakingAttempt, SpeakingMode } from '@/lib/types'

const router = useRouter()
const starting = ref<SpeakingMode | null>(null)
const error = ref('')
const history = ref<SpeakingAttempt[]>([])
const loadingHistory = ref(true)
const nextPage = ref<number | null>(null)

const modes: { mode: SpeakingMode; title: string; time: string; text: string; icon: unknown }[] = [
  {
    mode: 'full',
    title: 'Full speaking mock',
    time: '11–14 min',
    text: 'All three parts, just like the real test: interview, cue card with 1 minute to prepare, then discussion.',
    icon: Mic,
  },
  { mode: 'part1', title: 'Part 1 drill', time: '~4 min', text: 'Short questions about you, your home, work or studies.', icon: MessageCircle },
  { mode: 'part2', title: 'Part 2 drill', time: '~4 min', text: 'One cue card. 1 minute to prepare, up to 2 minutes to speak.', icon: NotebookPen },
  { mode: 'part3', title: 'Part 3 drill', time: '~5 min', text: 'Deeper discussion questions. Practise giving reasons and examples.', icon: MessagesSquare },
]

function modeTitle(mode: SpeakingMode) {
  return modes.find((m) => m.mode === mode)?.title ?? mode
}

async function start(mode: SpeakingMode) {
  error.value = ''
  starting.value = mode
  try {
    const { data } = await api.post<{ attempt: SpeakingAttempt }>('/api/speaking/attempts', { mode })
    router.push(`/speaking/test/${data.attempt.id}`)
  } catch (e) {
    if (!isLimitError(e)) error.value = errorMessage(e)
  } finally {
    starting.value = null
  }
}

async function loadHistory(page = 1) {
  try {
    const { data } = await api.get<Paginated<SpeakingAttempt>>('/api/speaking/attempts', { params: { page } })
    history.value = page === 1 ? data.data : [...history.value, ...data.data]
    nextPage.value = data.meta && data.meta.current_page < data.meta.last_page ? data.meta.current_page + 1 : null
  } catch {
    /* non-critical */
  } finally {
    loadingHistory.value = false
  }
}

onMounted(() => loadHistory())
</script>
