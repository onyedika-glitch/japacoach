<template>
  <div>
    <h1 class="page-title">Billing</h1>

    <Alert v-if="status === 'success'" tone="success" class="mt-6">
      <strong>Payment confirmed.</strong> Your plan is active — happy practising!
    </Alert>
    <Alert v-else-if="status === 'pending'" tone="warning" class="mt-6">
      <strong>Payment is still processing.</strong> This usually takes a minute. We'll activate your plan automatically once it's confirmed.
      <button class="underline font-semibold ml-1" @click="refresh">Check again</button>
    </Alert>
    <Alert v-else-if="status === 'failed'" tone="error" class="mt-6">
      <strong>Payment didn't go through.</strong> You weren't charged. Please try again or use a different payment method.
    </Alert>

    <div v-if="auth.user" class="card p-5 mt-6 flex flex-col sm:flex-row sm:items-center gap-4">
      <Crown class="w-8 h-8 text-brand shrink-0" />
      <div class="flex-1">
        <div class="font-semibold">Current plan: {{ planLabel(auth.user.plan) }}</div>
        <div class="text-sm text-muted">
          <template v-if="auth.user.is_premium && auth.user.plan_expires_at">
            Active until {{ date(auth.user.plan_expires_at) }}. Buying again adds days to your current plan.
          </template>
          <template v-else>Free plan — limited practice each month.</template>
        </div>
      </div>
    </div>

    <Products />

    <h2 class="section-title">Payment history</h2>
    <Spinner v-if="loadingPayments" block />
    <div v-else-if="payments.length" class="mt-3 card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-muted">
          <tr>
            <th class="p-3 font-medium">Date</th>
            <th class="p-3 font-medium">Plan</th>
            <th class="p-3 font-medium">Amount</th>
            <th class="p-3 font-medium">Status</th>
            <th class="p-3 font-medium hidden sm:table-cell">Reference</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in payments" :key="p.reference">
            <td class="p-3 whitespace-nowrap">{{ date(p.created_at) }}</td>
            <td class="p-3">{{ planLabel(p.plan) }}</td>
            <td class="p-3 whitespace-nowrap">{{ money(p.amount, p.currency) }}</td>
            <td class="p-3"><span class="chip" :class="statusClass(p.status)">{{ p.status }}</span></td>
            <td class="p-3 hidden sm:table-cell text-xs text-muted font-mono">{{ p.reference }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="mt-3 text-muted">No payments yet.</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Crown } from 'lucide-vue-next'
import Products from '@/components/Products.vue'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import { api } from '@/lib/api'
import { date, money, planLabel } from '@/lib/format'
import type { Payment } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const status = ref(typeof route.query.status === 'string' ? route.query.status : '')
const payments = ref<Payment[]>([])
const loadingPayments = ref(true)

function statusClass(s: string) {
  if (s === 'completed') return 'bg-good/10 text-good'
  if (s === 'pending') return 'bg-accent/15 text-[#b4730a]'
  return 'bg-bad/10 text-bad'
}

async function loadPayments() {
  try {
    const { data } = await api.get<{ data: Payment[] }>('/api/billing/payments')
    payments.value = data.data
  } catch {
    /* non-critical */
  } finally {
    loadingPayments.value = false
  }
}

async function refresh() {
  await Promise.all([auth.fetchMe(), loadPayments()])
  const latest = payments.value[0]
  if (latest?.status === 'completed') status.value = 'success'
  else if (latest?.status === 'failed') status.value = 'failed'
}

onMounted(async () => {
  await loadPayments()
  if (status.value) await auth.fetchMe()
})
</script>
