<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="page-title">Study plan</h1>
        <p class="text-muted mt-1">Built from your test date, target band and your weakest skills.</p>
      </div>
      <button v-if="plan" class="btn btn-secondary" :disabled="generating || !profileReady" @click="generate">
        <RefreshCw class="w-4 h-4" :class="generating ? 'animate-spin' : ''" /> {{ generating ? 'Rebuilding…' : 'Rebuild plan' }}
      </button>
    </div>

    <Spinner v-if="loading" block />

    <template v-else>
      <Alert v-if="!profileReady" tone="warning" class="mt-6">
        Set your <strong>target band</strong> and <strong>test date</strong> first so we can plan your weeks.
        <RouterLink to="/settings" class="font-semibold text-brand-dark ml-1">Go to settings →</RouterLink>
      </Alert>
      <Alert v-if="error" tone="error" class="mt-4">{{ error }}</Alert>

      <div v-if="!plan" class="card p-8 sm:p-12 mt-6 text-center">
        <CalendarCheck class="w-12 h-12 text-brand mx-auto" />
        <h2 class="font-grotesk text-xl font-bold mt-4">No plan yet</h2>
        <p class="text-muted mt-2 max-w-md mx-auto">
          Take at least one test in each module for the best plan, but you can generate one now and rebuild it later.
        </p>
        <button class="btn btn-primary mt-6" :disabled="generating || !profileReady" @click="generate">
          {{ generating ? 'Building your plan…' : 'Generate my study plan' }}
        </button>
      </div>

      <template v-else>
        <div class="card p-5 mt-6">
          <p class="text-[15px] leading-relaxed">{{ plan.summary }}</p>
          <p class="text-xs text-muted mt-2">Generated {{ dateTime(plan.generated_at) }}</p>
        </div>

        <div class="mt-6 space-y-6">
          <section v-for="w in plan.weeks" :key="w.week" class="card p-5">
            <div class="flex flex-wrap items-baseline gap-x-3">
              <h2 class="section-title">Week {{ w.week }}</h2>
              <span class="text-muted text-sm">{{ w.focus }}</span>
            </div>
            <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <div v-for="day in w.days" :key="day.day" class="rounded-xl bg-gray-50 p-3">
                <div class="text-sm font-semibold">{{ day.day }}</div>
                <ul class="mt-2 space-y-1.5">
                  <li v-for="(t, i) in day.tasks" :key="i" class="text-sm flex items-start gap-2">
                    <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :style="{ background: moduleColors[t.module] ?? '#94a3b8' }" />
                    <span class="flex-1">
                      <RouterLink v-if="moduleLinks[t.module]" :to="moduleLinks[t.module]" class="hover:text-brand-dark">{{ t.title }}</RouterLink>
                      <span v-else>{{ t.title }}</span>
                      <span class="text-muted"> · {{ t.minutes }} min</span>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>

        <section v-if="plan.tips?.length" class="card p-5 mt-6">
          <h2 class="section-title">Tips for you</h2>
          <ul class="mt-3 space-y-2 text-[15px]">
            <li v-for="(tip, i) in plan.tips" :key="i" class="flex gap-2"><Lightbulb class="w-5 h-5 text-accent shrink-0" />{{ tip }}</li>
          </ul>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarCheck, Lightbulb, RefreshCw } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'
import { dateTime } from '@/lib/format'
import { moduleColors } from '@/lib/palette'
import type { StudyPlan } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const plan = ref<StudyPlan | null>(null)
const loading = ref(true)
const generating = ref(false)
const error = ref('')

const profileReady = computed(() => !!auth.user?.target_band && !!auth.user?.test_date)
const moduleLinks: Record<string, string> = {
  speaking: '/speaking',
  writing: '/writing',
  reading: '/reading',
  listening: '/listening',
  mock: '/mock',
}

onMounted(async () => {
  try {
    const { data } = await api.get<{ plan: StudyPlan | null }>('/api/study-plan')
    plan.value = data.plan
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

async function generate() {
  generating.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ plan: StudyPlan }>('/api/study-plan/generate', {}, { timeout: 120000 })
    plan.value = data.plan
  } catch (e) {
    error.value = errorMessage(e, 'Could not build your plan. Please try again.')
  } finally {
    generating.value = false
  }
}
</script>
