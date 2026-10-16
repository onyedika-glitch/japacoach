<template>
  <div>
    <h1 class="page-title">My students</h1>
    <p class="text-muted mt-1">Track your students' practice and estimated bands. Students appear here when they join your class.</p>

    <Spinner v-if="loading" block />
    <Alert v-else-if="error" tone="error" class="mt-6">{{ error }}</Alert>

    <section v-for="c in centres" v-else :key="c.id" class="mt-8">
      <div class="card p-5 flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex-1">
          <h2 class="font-grotesk text-xl font-bold">{{ c.name }}</h2>
          <p class="text-sm text-muted">{{ c.students.length }} student{{ c.students.length === 1 ? '' : 's' }} · {{ activeCount(c) }} active in the last 7 days</p>
        </div>
        <div class="rounded-xl bg-brand-50 px-4 py-3">
          <div class="text-xs text-muted">Class code</div>
          <div class="font-mono text-lg font-bold tracking-wider">{{ c.join_code }}</div>
        </div>
        <button class="btn btn-secondary btn-sm" @click="copyInvite(c)">{{ copied === c.id ? 'Copied!' : 'Copy invite message' }}</button>
      </div>

      <div class="mt-4 card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-muted">
            <tr>
              <th class="p-3 font-medium">Student</th>
              <th class="p-3 font-medium text-center">Overall</th>
              <th class="p-3 font-medium text-center">Speaking</th>
              <th class="p-3 font-medium text-center">Writing</th>
              <th class="p-3 font-medium text-center">Reading</th>
              <th class="p-3 font-medium text-center">Listening</th>
              <th class="p-3 font-medium">Target · Test date</th>
              <th class="p-3 font-medium">Tests (30 days)</th>
              <th class="p-3 font-medium">Last active</th>
              <th class="p-3 font-medium">Plan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in c.students" :key="s.id">
              <td class="p-3">
                <div class="font-medium">{{ s.name }}</div>
                <div class="text-xs text-muted">{{ s.email }}</div>
              </td>
              <td v-for="m in modules" :key="m" class="p-3 text-center"><BandBadge :value="s.latest[m]" size="sm" /></td>
              <td class="p-3 whitespace-nowrap">{{ s.target_band ? band(s.target_band) : 'N/A' }} · {{ s.test_date ? date(s.test_date) : 'N/A' }}</td>
              <td class="p-3">{{ s.attempts_30d }}</td>
              <td class="p-3 whitespace-nowrap">{{ s.last_active_on ? date(s.last_active_on) : 'Never' }}</td>
              <td class="p-3 whitespace-nowrap">{{ planLabel(s.plan) }}<span v-if="s.plan_expires_at" class="text-xs text-muted block">until {{ date(s.plan_expires_at) }}</span></td>
            </tr>
            <tr v-if="!c.students.length">
              <td colspan="10" class="p-6 text-center text-muted">No students yet. Share your class code {{ c.join_code }} with them.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="c.students.length" class="mt-2 text-right">
        <button class="btn btn-ghost btn-sm" @click="exportStudents(c)">Download as CSV</button>
      </div>

      <template v-if="c.vouchers.length">
        <h3 class="section-title mt-8">Voucher codes</h3>
        <p class="text-sm text-muted">
          {{ unused(c).length }} unused of {{ c.vouchers.length }}. Give one code to each student; they redeem it at japacoach.com under Billing and join
          your class automatically.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button class="btn btn-secondary btn-sm" :disabled="!unused(c).length" @click="copyUnused(c)">{{ copied === -c.id ? 'Copied!' : 'Copy unused codes' }}</button>
          <button class="btn btn-ghost btn-sm" @click="exportVouchers(c)">Download all as CSV</button>
        </div>
        <div class="mt-3 card overflow-x-auto max-h-96 overflow-y-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-muted sticky top-0 bg-white">
              <tr>
                <th class="p-3 font-medium">Code</th>
                <th class="p-3 font-medium">Plan</th>
                <th class="p-3 font-medium">Used by</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="v in c.vouchers" :key="v.code">
                <td class="p-3 font-mono" :class="v.redeemed_at ? 'text-muted line-through' : ''">{{ v.code }}</td>
                <td class="p-3">{{ planLabel(v.plan) }} ({{ v.days }} days)</td>
                <td class="p-3">{{ v.redeemed_by ? `${v.redeemed_by} · ${date(v.redeemed_at)}` : 'Unused' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import BandBadge from '@/components/ui/BandBadge.vue'
import { api, errorMessage } from '@/lib/api'
import { downloadCsv } from '@/lib/csv'
import { band, date, planLabel } from '@/lib/format'

type ModuleKey = 'overall' | 'speaking' | 'writing' | 'reading' | 'listening'
interface Student {
  id: number
  name: string
  email: string
  joined_at: string | null
  plan: string
  plan_expires_at: string | null
  target_band: number | null
  test_date: string | null
  last_active_on: string | null
  latest: Record<ModuleKey, number | null>
  attempts_30d: number
}
interface TutorCentre {
  id: number
  name: string
  join_code: string
  students: Student[]
  vouchers: { code: string; plan: string; days: number; redeemed_by: string | null; redeemed_at: string | null }[]
}

const modules: ModuleKey[] = ['overall', 'speaking', 'writing', 'reading', 'listening']
const centres = ref<TutorCentre[]>([])
const loading = ref(true)
const error = ref('')
const copied = ref<number | null>(null)

const unused = (c: TutorCentre) => c.vouchers.filter((v) => !v.redeemed_at)
const activeCount = (c: TutorCentre) =>
  c.students.filter((s) => s.last_active_on && Date.now() - new Date(s.last_active_on).getTime() < 7 * 86400000).length

async function copy(text: string, marker: number) {
  await navigator.clipboard.writeText(text)
  copied.value = marker
  setTimeout(() => (copied.value = null), 2000)
}

function copyInvite(c: TutorCentre) {
  copy(
    `Join my class on Japa Coach to practise IELTS speaking, writing, reading and listening with an AI examiner.\n\n1. Sign up at https://japacoach.com/signup (or in the Japa Coach app)\n2. Go to Settings, then "Your class"\n3. Enter the code: ${c.join_code}`,
    c.id,
  )
}

function copyUnused(c: TutorCentre) {
  copy(unused(c).map((v) => v.code).join('\n'), -c.id)
}

function exportStudents(c: TutorCentre) {
  downloadCsv(
    `${c.name}-students.csv`,
    ['Name', 'Email', 'Overall', 'Speaking', 'Writing', 'Reading', 'Listening', 'Target band', 'Test date', 'Tests (30 days)', 'Last active', 'Plan'],
    c.students.map((s) => [s.name, s.email, ...modules.map((m) => s.latest[m]), s.target_band, s.test_date, s.attempts_30d, s.last_active_on, s.plan]),
  )
}

function exportVouchers(c: TutorCentre) {
  downloadCsv(
    `${c.name}-voucher-codes.csv`,
    ['Code', 'Plan', 'Days', 'Used by', 'Used on'],
    c.vouchers.map((v) => [v.code, planLabel(v.plan), v.days, v.redeemed_by, v.redeemed_at ? date(v.redeemed_at) : '']),
  )
}

onMounted(async () => {
  try {
    centres.value = (await api.get<{ data: TutorCentre[] }>('/api/tutor')).data.data
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your students.')
  } finally {
    loading.value = false
  }
})
</script>
