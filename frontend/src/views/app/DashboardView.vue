<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-muted">{{ greeting }},</p>
        <h1 class="page-title">{{ firstName }} 👋</h1>
      </div>
      <RouterLink to="/speaking" class="btn btn-primary"><Mic class="w-5 h-5" /> Practise speaking</RouterLink>
    </div>

    <Spinner v-if="loading" block label="Loading your dashboard…" />
    <Alert v-else-if="error" tone="error" class="mt-6">{{ error }}</Alert>

    <template v-else-if="d">
      <!-- Profile prompt -->
      <div v-if="!d.user.target_band || !d.user.test_date" class="mt-6 card p-5 flex flex-col sm:flex-row sm:items-center gap-4 border-accent/40 bg-accent/5">
        <Target class="w-8 h-8 text-accent shrink-0" />
        <div class="flex-1">
          <div class="font-semibold">Set your target band and test date</div>
          <div class="text-sm text-muted">We'll count down to your test and build a study plan around your weakest skills.</div>
        </div>
        <RouterLink to="/settings" class="btn btn-secondary btn-sm">Set it now</RouterLink>
      </div>

      <!-- Top stats -->
      <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5 col-span-2 lg:col-span-1 bg-ink text-white border-none">
          <div class="text-sm text-gray-300">Overall estimate</div>
          <div class="font-grotesk text-5xl font-bold mt-1">{{ band(d.latest.overall) }}</div>
          <div class="text-sm text-gray-300 mt-1">
            Target <span class="text-white font-semibold">{{ d.user.target_band ? band(d.user.target_band) : 'not set' }}</span>
          </div>
        </div>
        <div class="card p-5">
          <CalendarDays class="w-5 h-5 text-brand" />
          <div class="font-grotesk text-3xl font-bold mt-2">{{ d.days_to_test ?? 'N/A' }}</div>
          <div class="text-sm text-muted">{{ d.days_to_test === null ? 'No test date set' : 'days to your test' }}</div>
        </div>
        <div class="card p-5">
          <Flame class="w-5 h-5 text-accent" />
          <div class="font-grotesk text-3xl font-bold mt-2">{{ d.user.streak_days }}</div>
          <div class="text-sm text-muted">day streak</div>
        </div>
        <div class="card p-5 col-span-2 lg:col-span-1">
          <Crown class="w-5 h-5 text-brand" />
          <div class="font-grotesk text-xl font-bold mt-2">{{ planLabel(d.user.plan) }}</div>
          <div class="text-sm text-muted">
            <template v-if="d.user.is_premium && d.user.plan_expires_at">until {{ date(d.user.plan_expires_at) }}</template>
            <RouterLink v-else to="/billing" class="text-brand-dark font-semibold">Upgrade →</RouterLink>
          </div>
        </div>
      </div>

      <!-- Module bands -->
      <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <RouterLink v-for="m in modules" :key="m.key" :to="m.to" class="card p-5 hover:border-brand/40 transition group">
          <div class="flex items-center justify-between">
            <component :is="m.icon" class="w-6 h-6" :style="{ color: moduleColors[m.key] }" />
            <ChevronRight class="w-4 h-4 text-muted group-hover:text-ink" />
          </div>
          <div class="mt-3 text-sm text-muted">{{ m.label }}</div>
          <div class="font-grotesk text-3xl font-bold">{{ band(d.latest[m.key]) }}</div>
        </RouterLink>
      </div>

      <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <!-- Progress chart -->
        <div class="card p-5 lg:col-span-2">
          <div class="flex items-center justify-between">
            <h2 class="section-title">Progress</h2>
            <span class="text-xs text-muted">Estimated band per attempt</span>
          </div>
          <div v-if="d.history.length" class="mt-4">
            <ChartBox type="line" :data="chartData" :options="chartOptions" :height="260" />
          </div>
          <div v-else class="mt-6 text-center text-muted py-10">
            <LineChart class="w-10 h-10 mx-auto text-gray-300" />
            <p class="mt-2">Finish your first test to see your progress here.</p>
          </div>
        </div>

        <!-- Usage -->
        <div class="card p-5">
          <h2 class="section-title">Usage (last 30 days)</h2>
          <div class="mt-4 space-y-4">
            <UsageMeter label="Full speaking mocks" :usage="d.usage.speaking_mocks" />
            <UsageMeter label="Speaking drills" :usage="d.usage.speaking_drills" />
            <UsageMeter label="Writing evaluations" :usage="d.usage.writing" />
            <UsageMeter label="Reading tests" :usage="d.usage.reading" />
            <UsageMeter label="Listening tests" :usage="d.usage.listening" />
          </div>
          <RouterLink v-if="!d.user.is_premium" to="/billing" class="btn btn-secondary btn-sm w-full mt-5">Get more with a plan</RouterLink>
        </div>
      </div>

      <div class="mt-6 grid lg:grid-cols-2 gap-6">
        <!-- Recent attempts -->
        <div class="card p-5">
          <h2 class="section-title">Recent attempts</h2>
          <ul v-if="d.recent_attempts.length" class="mt-3 divide-y divide-gray-100">
            <li v-for="a in d.recent_attempts" :key="`${a.module}-${a.id}`">
              <RouterLink :to="attemptLink(a)" class="flex items-center gap-3 py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">{{ a.title }}</div>
                  <div class="text-xs text-muted">{{ moduleLabels[a.module] }} · {{ dateTime(a.created_at) }}</div>
                </div>
                <span v-if="a.status === 'processing'" class="chip bg-accent/15 text-[#b4730a]">Marking…</span>
                <span v-else-if="a.status === 'in_progress'" class="chip bg-gray-100 text-muted">In progress</span>
                <span v-else-if="a.status === 'failed'" class="chip bg-bad/10 text-bad">Failed</span>
                <BandBadge v-else :value="a.band" size="sm" />
              </RouterLink>
            </li>
          </ul>
          <p v-else class="mt-4 text-muted">No attempts yet. <RouterLink to="/speaking" class="text-brand-dark font-semibold">Start with a speaking drill →</RouterLink></p>
        </div>

        <!-- Study plan preview -->
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <h2 class="section-title">This week's plan</h2>
            <RouterLink to="/study-plan" class="text-sm font-semibold text-brand-dark">Open plan →</RouterLink>
          </div>
          <template v-if="d.study_plan && d.study_plan.weeks.length">
            <p class="text-sm text-muted mt-2">{{ d.study_plan.weeks[0].focus }}</p>
            <ul class="mt-3 space-y-2">
              <li v-for="day in d.study_plan.weeks[0].days.slice(0, 4)" :key="day.day" class="text-sm">
                <span class="font-semibold">{{ day.day }}:</span>
                <span class="text-muted"> {{ day.tasks.map((t) => t.title).join(' · ') }}</span>
              </li>
            </ul>
          </template>
          <p v-else class="mt-3 text-muted text-sm">
            No study plan yet. Set your target band and test date, then generate one in a click.
          </p>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { BookOpen, CalendarDays, ChevronRight, Crown, Flame, Headphones, LineChart, Mic, PenLine, Target } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import UsageMeter from '@/components/ui/UsageMeter.vue'
import ChartBox from '@/components/ui/ChartBox.vue'
import { api, errorMessage } from '@/lib/api'
import { band, date, dateTime, moduleLabels, planLabel } from '@/lib/format'
import { moduleColors } from '@/lib/palette'
import type { Dashboard, Module } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const d = ref<Dashboard | null>(null)
const loading = ref(true)
const error = ref('')

const firstName = computed(() => (d.value?.user.name ?? auth.user?.name ?? '').split(' ')[0])
const greeting = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'
})

const modules: { key: Module; label: string; to: string; icon: unknown }[] = [
  { key: 'speaking', label: 'Speaking', to: '/speaking', icon: Mic },
  { key: 'writing', label: 'Writing', to: '/writing', icon: PenLine },
  { key: 'reading', label: 'Reading', to: '/reading', icon: BookOpen },
  { key: 'listening', label: 'Listening', to: '/listening', icon: Headphones },
]

const chartData = computed(() => {
  const history = d.value?.history ?? []
  const dates = [...new Set(history.map((h) => h.date.slice(0, 10)))].sort()
  const labels = dates.map((x) => date(x, { day: 'numeric', month: 'short' }))
  const datasets: Record<string, unknown>[] = modules
    .map((m) => {
      const byDate = new Map<string, number>()
      history.filter((h) => h.module === m.key).forEach((h) => byDate.set(h.date.slice(0, 10), h.band))
      return {
        label: m.label,
        data: dates.map((x) => byDate.get(x) ?? null),
        borderColor: moduleColors[m.key],
        backgroundColor: moduleColors[m.key],
        spanGaps: true,
        tension: 0.25,
        pointRadius: 4,
        borderWidth: 2,
      }
    })
    .filter((ds) => ds.data.some((v) => v !== null))
  if (d.value?.user.target_band) {
    datasets.push({
      label: 'Target',
      data: dates.map(() => d.value!.user.target_band),
      borderColor: '#94a3b8',
      backgroundColor: '#94a3b8',
      spanGaps: true,
      tension: 0,
      pointRadius: 0,
      borderWidth: 1.5,
      borderDash: [6, 6],
    })
  }
  return { labels, datasets }
})

const chartOptions = {
  scales: {
    y: { min: 3, max: 9, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
    x: { grid: { display: false } },
  },
  interaction: { mode: 'nearest', intersect: false },
}

function attemptLink(a: Dashboard['recent_attempts'][number]): string {
  if (a.module === 'speaking') return a.status === 'in_progress' ? `/speaking/test/${a.id}` : `/speaking/report/${a.id}`
  if (a.module === 'writing') return `/writing/report/${a.id}`
  return a.status === 'in_progress' ? `/${a.module}/test/${a.id}` : `/practice/result/${a.id}`
}

onMounted(async () => {
  try {
    const { data } = await api.get<Dashboard>('/api/dashboard')
    d.value = data
    auth.setUser(data.user)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})
</script>
