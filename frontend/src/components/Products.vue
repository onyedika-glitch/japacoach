<template>
  <section id="pricing" class="px-4 sm:px-6 py-20 md:py-28 scroll-mt-16">
    <div class="max-w-6xl mx-auto">
      <div class="text-center max-w-2xl mx-auto">
        <h4 class="text-brand-dark font-semibold">Pricing</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em]">Less than one prep class</h2>
        <p class="mt-4 text-lg text-muted">Start free. Pay only when you're ready, with no automatic debits.</p>
      </div>

      <div v-if="loading" class="mt-14 grid md:grid-cols-4 gap-5">
        <div v-for="i in 4" :key="i" class="card h-80 animate-pulse bg-gray-50" />
      </div>
      <p v-else-if="error" class="mt-10 text-center text-muted">{{ error }}</p>

      <div v-else class="mt-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 items-stretch">
        <!-- Free -->
        <div class="card p-6 flex flex-col">
          <h3 class="font-grotesk text-lg font-semibold">Free</h3>
          <div class="mt-3 font-grotesk text-4xl font-bold">{{ money(0, currency) }}</div>
          <div class="text-sm text-muted">forever</div>
          <ul class="mt-6 space-y-2.5 text-[15px] flex-1">
            <li v-for="f in freeFeatures" :key="f" class="flex gap-2"><Check class="w-5 h-5 text-good shrink-0" />{{ f }}</li>
          </ul>
          <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/signup'" class="btn btn-secondary mt-6">Start free</RouterLink>
        </div>

        <div
          v-for="p in plans"
          :key="p.id"
          class="card p-6 flex flex-col relative"
          :class="p.highlight ? 'border-2 !border-brand shadow-[0_20px_40px_rgba(1,184,198,0.18)]' : ''"
        >
          <span v-if="p.highlight" class="chip bg-brand text-white absolute -top-3 left-6">Most popular</span>
          <h3 class="font-grotesk text-lg font-semibold">{{ p.name }}</h3>
          <div class="mt-3 font-grotesk text-4xl font-bold">{{ money(p.price, currency) }}</div>
          <div class="text-sm text-muted">for {{ p.days }} days</div>
          <ul class="mt-6 space-y-2.5 text-[15px] flex-1">
            <li v-for="f in p.features" :key="f" class="flex gap-2"><Check class="w-5 h-5 text-good shrink-0" />{{ f }}</li>
          </ul>
          <button class="btn mt-6" :class="p.highlight ? 'btn-primary' : 'btn-secondary'" :disabled="buying === p.id" @click="choose(p.id)">
            {{ buying === p.id ? 'Redirecting…' : `Get ${p.name}` }}
          </button>
        </div>
      </div>
      <p v-if="checkoutError" class="mt-4 text-center text-bad text-sm">{{ checkoutError }}</p>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Check } from 'lucide-vue-next'
import { api, errorMessage } from '@/lib/api'
import { money } from '@/lib/format'
import type { Plan, PlansResponse } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const plans = ref<Plan[]>([])
const currency = ref('NGN')
const freeLimits = ref<Record<string, number | null>>({})
const loading = ref(true)
const error = ref('')
const buying = ref<string | null>(null)
const checkoutError = ref('')

const freeFeatures = computed(() => {
  const l = freeLimits.value
  const n = (k: string, label: string) => (l[k] === null ? `Unlimited ${label}` : `${l[k] ?? 0} ${label}`)
  if (!Object.keys(l).length) return ['Speaking drills', 'Writing feedback', 'Reading & listening tests']
  return [
    n('speaking_drills', 'speaking drills / month'),
    n('speaking_mocks', 'full speaking mocks / month'),
    n('writing', 'writing evaluations / month'),
    n('reading', 'reading tests / month'),
    n('listening', 'listening tests / month'),
  ]
})

onMounted(async () => {
  try {
    const { data } = await api.get<PlansResponse>('/api/billing/plans')
    plans.value = data.plans
    currency.value = data.currency
    freeLimits.value = data.free_limits ?? {}
  } catch (e) {
    error.value = errorMessage(e, 'Could not load prices right now.')
  } finally {
    loading.value = false
  }
})

async function choose(plan: string) {
  if (!auth.isLoggedIn) {
    router.push({ name: 'signup', query: { redirect: '/billing' } })
    return
  }
  checkoutError.value = ''
  buying.value = plan
  try {
    const { data } = await api.post<{ checkout_url: string }>('/api/billing/checkout', { plan })
    window.location.href = data.checkout_url
  } catch (e) {
    checkoutError.value = errorMessage(e, 'Could not start checkout. Please try again.')
    buying.value = null
  }
}
</script>
