<template>
  <div class="max-w-2xl">
    <h1 class="page-title">Settings</h1>

    <form class="card p-5 sm:p-6 mt-6 space-y-5" @submit.prevent="save">
      <h2 class="section-title">Your IELTS profile</h2>

      <div>
        <label class="label">Test type</label>
        <div class="grid grid-cols-2 gap-3">
          <label
            v-for="t in testTypes"
            :key="t.value"
            class="rounded-xl border p-3 cursor-pointer transition"
            :class="form.test_type === t.value ? 'border-brand bg-brand-50' : 'border-gray-200'"
          >
            <input v-model="form.test_type" type="radio" :value="t.value" class="sr-only" />
            <div class="font-semibold">{{ t.label }}</div>
            <div class="text-xs text-muted mt-0.5">{{ t.hint }}</div>
          </label>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label" for="target">Target band</label>
          <select id="target" v-model="form.target_band" class="input">
            <option :value="null">Not set</option>
            <option v-for="b in bands" :key="b" :value="b">{{ b.toFixed(1) }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="date">Test date</label>
          <input id="date" v-model="form.test_date" type="date" class="input" :min="today" />
        </div>
      </div>

      <h2 class="section-title pt-2">Account</h2>
      <div>
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" class="input" required />
      </div>
      <div>
        <label class="label">Email</label>
        <input :value="auth.user?.email" class="input bg-gray-50 text-muted" disabled />
      </div>

      <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 cursor-pointer">
        <input v-model="form.email_reminders" type="checkbox" class="accent-brand w-5 h-5 mt-0.5" />
        <span>
          <span class="font-semibold block">Email reminders</span>
          <span class="text-sm text-muted">Study nudges, "your report is ready" and plan-ending reminders.</span>
        </span>
      </label>

      <Alert v-if="message" :tone="messageTone">{{ message }}</Alert>
      <button class="btn btn-primary w-full sm:w-auto" :disabled="saving">{{ saving ? 'Saving…' : 'Save changes' }}</button>
    </form>

    <ReviewPrompt standalone class="mt-6" />

    <div class="card p-5 sm:p-6 mt-6">
      <h2 class="section-title">Delete account</h2>
      <p class="text-sm text-muted mt-1">Permanently remove your account and all your practice data.</p>
      <RouterLink to="/delete-account" class="btn btn-secondary btn-sm mt-4 !text-bad">Delete my account</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, watchEffect } from 'vue'
import ReviewPrompt from '@/components/ReviewPrompt.vue'
import Alert from '@/components/ui/Alert.vue'
import { errorMessage } from '@/lib/api'
import type { TestType } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const today = new Date().toISOString().slice(0, 10)
const bands = Array.from({ length: 11 }, (_, i) => 4 + i * 0.5)
const testTypes = [
  { value: 'academic' as TestType, label: 'Academic', hint: 'University study, professional registration' },
  { value: 'general' as TestType, label: 'General Training', hint: 'Work, migration (e.g. Canada, UK, Australia)' },
]

const form = reactive({
  name: '',
  test_type: 'academic' as TestType,
  target_band: null as number | null,
  test_date: '' as string,
  email_reminders: true,
})
const saving = ref(false)
const message = ref('')
const messageTone = ref<'success' | 'error'>('success')

watchEffect(() => {
  const u = auth.user
  if (!u) return
  form.name = u.name
  form.test_type = u.test_type
  form.target_band = u.target_band !== null ? Number(u.target_band) : null
  form.test_date = u.test_date ?? ''
  form.email_reminders = u.email_reminders
})

async function save() {
  saving.value = true
  message.value = ''
  try {
    await auth.update({
      name: form.name,
      test_type: form.test_type,
      target_band: form.target_band,
      test_date: form.test_date || null,
      email_reminders: form.email_reminders,
    })
    messageTone.value = 'success'
    message.value = 'Saved.'
  } catch (e) {
    messageTone.value = 'error'
    message.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>
