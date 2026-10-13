<template>
  <div>
    <h1 class="page-title">Admin</h1>

    <div class="mt-5 flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="t in tabs"
        :key="t.key"
        class="shrink-0 px-4 h-10 rounded-xl text-sm font-semibold border"
        :class="tab === t.key ? 'bg-ink text-white border-ink' : 'bg-white border-gray-200 text-muted'"
        @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </div>
    <Alert v-if="error" tone="error" class="mt-4">{{ error }}</Alert>

    <!-- STATS -->
    <section v-if="tab === 'stats'" class="mt-6">
      <Spinner v-if="!stats" block />
      <template v-else>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Stat label="Users" :value="stats.users_total" :sub="`+${stats.users_today} today`" />
          <Stat label="Active premium" :value="stats.premium_active" />
          <Stat label="Revenue (30 days)" :value="money(stats.revenue_30d, stats.currency)" />
          <Stat label="Revenue (all time)" :value="money(stats.revenue_total, stats.currency)" />
        </div>
        <h2 class="section-title mt-8">Attempts in the last 7 days</h2>
        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Stat v-for="(v, k) in stats.attempts_7d" :key="k" :label="moduleLabels[k] ?? k" :value="v" />
        </div>
        <h2 class="section-title mt-8">Content</h2>
        <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Stat label="Speaking questions" :value="stats.content.speaking_questions" :sub="`${stats.content.speaking_missing_audio} missing audio`" />
          <Stat label="Writing tasks" :value="stats.content.writing_tasks" />
          <Stat label="Reading tests" :value="stats.content.reading_tests" />
          <Stat label="Listening tests" :value="stats.content.listening_tests" :sub="`${stats.content.listening_missing_audio} missing audio`" />
        </div>
      </template>
    </section>

    <!-- USERS -->
    <section v-else-if="tab === 'users'" class="mt-6">
      <form class="flex gap-2" @submit.prevent="loadUsers(1)">
        <input v-model="search" class="input h-10" placeholder="Search name or email" />
        <button class="btn btn-secondary btn-sm h-10">Search</button>
      </form>
      <Spinner v-if="usersLoading" block />
      <div v-else class="mt-4 card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-muted">
            <tr>
              <th class="p-3 font-medium">User</th>
              <th class="p-3 font-medium">Plan</th>
              <th class="p-3 font-medium">Expires</th>
              <th class="p-3 font-medium">Attempts</th>
              <th class="p-3 font-medium">Joined</th>
              <th class="p-3 font-medium"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="u in users" :key="u.id">
              <td class="p-3">
                <div class="font-medium">{{ u.name }} <span v-if="u.is_admin" class="chip bg-ink text-white ml-1">admin</span></div>
                <div class="text-xs text-muted">{{ u.email }}</div>
              </td>
              <td class="p-3">
                <select class="input h-9 text-sm w-32" :value="u.plan" @change="updateUser(u, { plan: ($event.target as HTMLSelectElement).value })">
                  <option v-for="p in planIds" :key="p" :value="p">{{ planLabel(p) }}</option>
                </select>
              </td>
              <td class="p-3">
                <input
                  type="date"
                  class="input h-9 text-sm w-40"
                  :value="u.plan_expires_at ? u.plan_expires_at.slice(0, 10) : ''"
                  @change="updateUser(u, { plan_expires_at: ($event.target as HTMLInputElement).value || null })"
                />
              </td>
              <td class="p-3">{{ u.attempts_count }}</td>
              <td class="p-3 whitespace-nowrap">{{ date(u.created_at) }}</td>
              <td class="p-3">
                <button class="btn btn-ghost btn-sm" @click="updateUser(u, { is_admin: !u.is_admin })">{{ u.is_admin ? 'Remove admin' : 'Make admin' }}</button>
              </td>
            </tr>
            <tr v-if="!users.length"><td colspan="6" class="p-6 text-center text-muted">No users found.</td></tr>
          </tbody>
        </table>
      </div>
      <Pager :meta="usersMeta" @page="loadUsers" />
    </section>

    <!-- PAYMENTS -->
    <section v-else-if="tab === 'payments'" class="mt-6">
      <div class="flex gap-2">
        <select v-model="paymentStatus" class="input h-10 w-44" @change="loadPayments(1)">
          <option value="">All statuses</option>
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
          <option value="failed">Failed</option>
        </select>
      </div>
      <Spinner v-if="paymentsLoading" block />
      <div v-else class="mt-4 card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-muted">
            <tr>
              <th class="p-3 font-medium">Date</th>
              <th class="p-3 font-medium">User</th>
              <th class="p-3 font-medium">Plan</th>
              <th class="p-3 font-medium">Amount</th>
              <th class="p-3 font-medium">Status</th>
              <th class="p-3 font-medium">Reference</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in adminPayments" :key="p.id">
              <td class="p-3 whitespace-nowrap">{{ dateTime(p.created_at) }}</td>
              <td class="p-3">
                <div>{{ p.user?.name }}</div>
                <div class="text-xs text-muted">{{ p.user?.email }}</div>
              </td>
              <td class="p-3">{{ planLabel(p.plan) }}</td>
              <td class="p-3 whitespace-nowrap">{{ money(p.amount, p.currency) }}</td>
              <td class="p-3">{{ p.status }}</td>
              <td class="p-3 text-xs font-mono text-muted">{{ p.reference }}</td>
            </tr>
            <tr v-if="!adminPayments.length"><td colspan="6" class="p-6 text-center text-muted">No payments.</td></tr>
          </tbody>
        </table>
      </div>
      <Pager :meta="paymentsMeta" @page="loadPayments" />
    </section>

    <!-- CONTENT -->
    <section v-else-if="tab === 'content'" class="mt-6">
      <div class="card p-5">
        <h2 class="section-title">Generate content with AI</h2>
        <p class="text-sm text-muted mt-1">Runs in the background. New items are unpublished until you review them. "Audio" renders missing examiner and listening audio.</p>
        <div class="mt-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="label">Type</label>
            <select v-model="gen.type" class="input h-10 w-40">
              <option value="speaking">Speaking questions</option>
              <option value="writing">Writing tasks</option>
              <option value="reading">Reading test</option>
              <option value="listening">Listening test</option>
              <option value="audio">Missing audio</option>
            </select>
          </div>
          <div v-if="gen.type !== 'audio'">
            <label class="label">Count</label>
            <input v-model.number="gen.count" type="number" min="1" max="20" class="input h-10 w-24" />
          </div>
          <div v-if="gen.type === 'writing' || gen.type === 'reading'">
            <label class="label">Test type</label>
            <select v-model="gen.test_type" class="input h-10 w-36">
              <option value="academic">Academic</option>
              <option value="general">General</option>
            </select>
          </div>
          <button class="btn btn-primary btn-sm h-10" :disabled="generating" @click="generate">{{ generating ? 'Queuing…' : 'Generate' }}</button>
        </div>
        <p v-if="genMessage" class="text-sm text-good mt-3">{{ genMessage }}</p>
      </div>

      <Spinner v-if="!content" block />
      <template v-else>
        <ContentTable
          title="Speaking questions"
          :rows="content.speaking_questions.map((q) => ({ id: q.id, published: q.is_published, cols: [`Part ${q.part}`, q.topic, q.prompt, q.has_audio ? '🔊' : 'No audio'] }))"
          :headers="['Part', 'Topic', 'Prompt', 'Audio']"
          @toggle="(id, v) => togglePublish('speaking', id, v)"
        />
        <ContentTable
          title="Writing tasks"
          :rows="content.writing_tasks.map((t) => ({ id: t.id, published: t.is_published, cols: [`Task ${t.task_number}`, t.test_type, t.title] }))"
          :headers="['Task', 'Type', 'Title']"
          @toggle="(id, v) => togglePublish('writing', id, v)"
        />
        <ContentTable
          title="Reading & listening tests"
          :rows="
            content.practice_tests.map((t) => ({
              id: t.id,
              published: t.is_published,
              cols: [t.module, t.test_type, t.title, String(t.question_count), t.missing_audio ? `${t.missing_audio} missing` : 'Ready'],
            }))
          "
          :headers="['Module', 'Type', 'Title', 'Qs', 'Audio']"
          @toggle="(id, v) => togglePublish('practice', id, v)"
        />
      </template>
    </section>
  </div>
</template>

<script setup lang="ts">
import { defineComponent, h, onMounted, reactive, ref, watch, type PropType } from 'vue'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'
import { date, dateTime, moduleLabels, money, planLabel } from '@/lib/format'

type Tab = 'stats' | 'users' | 'payments' | 'content'
interface Meta {
  current_page: number
  last_page: number
  total: number
}
interface AdminStats {
  users_total: number
  users_today: number
  premium_active: number
  revenue_total: number
  revenue_30d: number
  currency: string
  attempts_7d: Record<string, number>
  content: {
    speaking_questions: number
    writing_tasks: number
    reading_tests: number
    listening_tests: number
    listening_missing_audio: number
    speaking_missing_audio: number
  }
}
interface AdminUser {
  id: number
  name: string
  email: string
  plan: string
  plan_expires_at: string | null
  is_admin: boolean
  created_at: string
  attempts_count: number
}
interface AdminPayment {
  id: number
  reference: string
  user: { id: number; name: string; email: string } | null
  plan: string
  amount: number
  currency: string
  status: string
  created_at: string
}
interface AdminContent {
  speaking_questions: { id: number; part: number; topic: string; prompt: string; is_published: boolean; has_audio: boolean }[]
  writing_tasks: { id: number; task_number: number; test_type: string; title: string; is_published: boolean }[]
  practice_tests: { id: number; module: string; test_type: string; title: string; is_published: boolean; question_count: number; missing_audio: number }[]
}

const tabs: { key: Tab; label: string }[] = [
  { key: 'stats', label: 'Overview' },
  { key: 'users', label: 'Users' },
  { key: 'payments', label: 'Payments' },
  { key: 'content', label: 'Content' },
]
const planIds = ['free', 'weekly', 'monthly', 'sprint']

const tab = ref<Tab>('stats')
const error = ref('')
const stats = ref<AdminStats | null>(null)

const search = ref('')
const users = ref<AdminUser[]>([])
const usersMeta = ref<Meta | null>(null)
const usersLoading = ref(false)

const paymentStatus = ref('')
const adminPayments = ref<AdminPayment[]>([])
const paymentsMeta = ref<Meta | null>(null)
const paymentsLoading = ref(false)

const content = ref<AdminContent | null>(null)
const gen = reactive({ type: 'speaking', count: 5, test_type: 'academic' })
const generating = ref(false)
const genMessage = ref('')

async function guard(fn: () => Promise<void>) {
  error.value = ''
  try {
    await fn()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

const loadStats = () =>
  guard(async () => {
    stats.value = (await api.get<AdminStats>('/api/admin/stats')).data
  })

async function loadUsers(page = 1) {
  usersLoading.value = true
  await guard(async () => {
    const { data } = await api.get<{ data: AdminUser[]; meta: Meta }>('/api/admin/users', { params: { search: search.value || undefined, page } })
    users.value = data.data
    usersMeta.value = data.meta
  })
  usersLoading.value = false
}

async function loadPayments(page = 1) {
  paymentsLoading.value = true
  await guard(async () => {
    const { data } = await api.get<{ data: AdminPayment[]; meta: Meta }>('/api/admin/payments', {
      params: { status: paymentStatus.value || undefined, page },
    })
    adminPayments.value = data.data
    paymentsMeta.value = data.meta
  })
  paymentsLoading.value = false
}

const loadContent = () =>
  guard(async () => {
    content.value = (await api.get<AdminContent>('/api/admin/content')).data
  })

async function updateUser(u: AdminUser, patch: Partial<Pick<AdminUser, 'plan' | 'plan_expires_at' | 'is_admin'>>) {
  if (patch.is_admin !== undefined && !window.confirm(patch.is_admin ? `Make ${u.email} an admin?` : `Remove admin from ${u.email}?`)) return
  await guard(async () => {
    await api.patch(`/api/admin/users/${u.id}`, patch)
    Object.assign(u, patch)
  })
}

async function generate() {
  generating.value = true
  genMessage.value = ''
  await guard(async () => {
    const payload: Record<string, unknown> = { type: gen.type }
    if (gen.type !== 'audio') payload.count = gen.count
    if (gen.type === 'writing' || gen.type === 'reading') payload.test_type = gen.test_type
    const { data } = await api.post<{ message: string }>('/api/admin/content/generate', payload)
    genMessage.value = data.message || 'Queued.'
  })
  generating.value = false
}

async function togglePublish(type: 'speaking' | 'writing' | 'practice', id: number, value: boolean) {
  await guard(async () => {
    await api.patch(`/api/admin/content/${type}/${id}`, { is_published: value })
    await loadContent()
  })
}

watch(
  tab,
  (t) => {
    if (t === 'stats') loadStats()
    if (t === 'users' && !users.value.length) loadUsers()
    if (t === 'payments' && !adminPayments.value.length) loadPayments()
    if (t === 'content') loadContent()
  },
  { immediate: false },
)

onMounted(loadStats)

const Stat = defineComponent({
  props: { label: { type: String, required: true }, value: { type: [String, Number], required: true }, sub: String },
  setup(props) {
    return () =>
      h('div', { class: 'card p-5' }, [
        h('div', { class: 'text-sm text-muted' }, props.label),
        h('div', { class: 'font-grotesk text-2xl font-bold mt-1' }, String(props.value)),
        props.sub ? h('div', { class: 'text-xs text-muted mt-1' }, props.sub) : null,
      ])
  },
})

const Pager = defineComponent({
  props: { meta: { type: Object as PropType<Meta | null>, default: null } },
  emits: ['page'],
  setup(props, { emit }) {
    return () => {
      const m = props.meta
      if (!m || m.last_page <= 1) return null
      return h('div', { class: 'mt-4 flex items-center justify-center gap-3 text-sm' }, [
        h('button', { class: 'btn btn-secondary btn-sm', disabled: m.current_page <= 1, onClick: () => emit('page', m.current_page - 1) }, 'Previous'),
        h('span', { class: 'text-muted' }, `Page ${m.current_page} of ${m.last_page} · ${m.total} total`),
        h('button', { class: 'btn btn-secondary btn-sm', disabled: m.current_page >= m.last_page, onClick: () => emit('page', m.current_page + 1) }, 'Next'),
      ])
    }
  },
})

const ContentTable = defineComponent({
  props: {
    title: { type: String, required: true },
    headers: { type: Array as PropType<string[]>, required: true },
    rows: { type: Array as PropType<{ id: number; published: boolean; cols: string[] }[]>, required: true },
  },
  emits: ['toggle'],
  setup(props, { emit }) {
    return () =>
      h('div', { class: 'mt-6' }, [
        h('h2', { class: 'section-title' }, `${props.title} (${props.rows.length})`),
        h('div', { class: 'mt-3 card overflow-x-auto max-h-[28rem] overflow-y-auto' }, [
          h('table', { class: 'w-full text-sm' }, [
            h('thead', { class: 'text-left text-muted sticky top-0 bg-white' }, [
              h('tr', [...props.headers.map((x) => h('th', { class: 'p-3 font-medium' }, x)), h('th', { class: 'p-3 font-medium' }, 'Published')]),
            ]),
            h(
              'tbody',
              { class: 'divide-y divide-gray-100' },
              props.rows.length
                ? props.rows.map((r) =>
                    h('tr', { key: r.id }, [
                      ...r.cols.map((c, i) => h('td', { class: `p-3 ${i === 2 ? 'max-w-md truncate' : 'whitespace-nowrap'}`, title: c }, c)),
                      h('td', { class: 'p-3' }, [
                        h('input', {
                          type: 'checkbox',
                          class: 'accent-brand w-4 h-4',
                          checked: r.published,
                          onChange: (e: Event) => emit('toggle', r.id, (e.target as HTMLInputElement).checked),
                        }),
                      ]),
                    ]),
                  )
                : [h('tr', [h('td', { class: 'p-6 text-center text-muted', colspan: props.headers.length + 1 }, 'Nothing yet.')])],
            ),
          ]),
        ]),
      ])
  },
})
</script>
