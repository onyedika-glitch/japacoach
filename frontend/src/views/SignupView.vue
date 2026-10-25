<template>
  <AuthCard title="Take your free speaking test" subtitle="Create a free account. No card needed.">
    <div v-if="referral" class="mb-5 rounded-xl bg-accent/10 text-sm p-3 flex gap-2">
      <Gift class="w-5 h-5 text-accent shrink-0" />
      <span>You were invited by a friend, so you'll get a bonus full speaking mock when you join.</span>
    </div>
    <GoogleButton :href="googleHref" />
    <Divider />
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="name">Full name</label>
        <input id="name" v-model="name" class="input" autocomplete="name" required />
      </div>
      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model="email" type="email" class="input" autocomplete="email" required />
      </div>
      <div>
        <span class="label">Which IELTS are you taking?</span>
        <div class="grid grid-cols-2 gap-2">
          <label
            v-for="t in testTypes"
            :key="t.value"
            class="rounded-xl border p-3 cursor-pointer transition"
            :class="testType === t.value ? 'border-brand bg-brand-50' : 'border-gray-200'"
          >
            <input v-model="testType" type="radio" :value="t.value" class="sr-only" />
            <span class="font-semibold text-sm block">{{ t.label }}</span>
            <span class="text-xs text-muted">{{ t.hint }}</span>
          </label>
        </div>
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <PasswordInput id="password" v-model="password" autocomplete="new-password" minlength="8" required />
        <p class="text-xs text-muted mt-1">At least 8 characters.</p>
      </div>
      <div>
        <label class="label" for="password2">Confirm password</label>
        <PasswordInput id="password2" v-model="password2" autocomplete="new-password" required />
      </div>
      <p v-if="error" class="text-sm text-bad">{{ error }}</p>
      <button class="btn btn-primary w-full" :disabled="busy">{{ busy ? 'Creating account…' : 'Create free account' }}</button>
      <p class="text-xs text-muted text-center">
        By signing up you agree to our <RouterLink to="/terms" class="underline">Terms</RouterLink> and
        <RouterLink to="/privacy" class="underline">Privacy policy</RouterLink>.
      </p>
    </form>
    <p class="text-center text-sm text-muted mt-6">
      Already have an account? <RouterLink to="/login" class="font-semibold text-brand-dark">Log in</RouterLink>
    </p>
  </AuthCard>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Gift } from 'lucide-vue-next'
import AuthCard from '@/components/auth/AuthCard.vue'
import GoogleButton from '@/components/auth/GoogleButton.vue'
import Divider from '@/components/auth/Divider.vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/api'
import type { TestType } from '@/lib/types'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const referral = computed(() => {
  const fromUrl = typeof route.query.ref === 'string' ? route.query.ref : ''
  if (fromUrl) {
    try {
      localStorage.setItem('jc_ref', fromUrl)
    } catch {
      /* ignore */
    }
    return fromUrl
  }
  try {
    return localStorage.getItem('jc_ref') ?? ''
  } catch {
    return ''
  }
})
const googleHref = computed(() => (referral.value ? `/auth/google?ref=${encodeURIComponent(referral.value)}` : '/auth/google'))

const name = ref('')
const email = ref('')
const password = ref('')
const password2 = ref('')
const testType = ref<TestType>('academic')
const testTypes: { value: TestType; label: string; hint: string }[] = [
  { value: 'academic', label: 'Academic', hint: 'University, nursing, professional registration' },
  { value: 'general', label: 'General Training', hint: 'Work, migration (UK, Canada, Australia)' },
]
const busy = ref(false)
const error = ref('')

async function submit() {
  if (password.value !== password2.value) {
    error.value = 'Passwords do not match.'
    return
  }
  busy.value = true
  error.value = ''
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: password2.value,
      test_type: testType.value,
      ...(referral.value ? { referral_code: referral.value } : {}),
    })
    try {
      localStorage.removeItem('jc_ref')
    } catch {
      /* ignore */
    }
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/speaking'
    router.replace(redirect)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>
