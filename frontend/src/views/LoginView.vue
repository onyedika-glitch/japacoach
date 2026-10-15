<template>
  <AuthCard title="Welcome back" subtitle="Log in to continue practising.">
    <GoogleButton :href="googleHref" />
    <Divider />
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model="email" type="email" class="input" autocomplete="email" required />
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <PasswordInput id="password" v-model="password" autocomplete="current-password" required />
      </div>
      <label class="flex items-center gap-2 text-sm text-muted">
        <input v-model="remember" type="checkbox" class="accent-brand w-4 h-4" /> Keep me signed in
      </label>
      <p v-if="error" class="text-sm text-bad">{{ error }}</p>
      <button class="btn btn-primary w-full" :disabled="busy">{{ busy ? 'Logging in…' : 'Log in' }}</button>
    </form>
    <p class="text-center text-sm text-muted mt-6">
      New here? <RouterLink to="/signup" class="font-semibold text-brand-dark">Create a free account</RouterLink>
    </p>
  </AuthCard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthCard from '@/components/auth/AuthCard.vue'
import GoogleButton from '@/components/auth/GoogleButton.vue'
import Divider from '@/components/auth/Divider.vue'
import PasswordInput from '@/components/auth/PasswordInput.vue'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/api'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const remember = ref(true)
const busy = ref(false)
const error = ref(route.query.error === 'google' ? 'Google sign-in failed. Please try again or use email.' : '')
const googleHref = '/auth/google'

async function submit() {
  busy.value = true
  error.value = ''
  try {
    await auth.login({ email: email.value, password: password.value, remember: remember.value })
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/dashboard'
    router.replace(redirect)
  } catch (e) {
    error.value = errorMessage(e, 'Those details did not match. Please try again.')
  } finally {
    busy.value = false
  }
}
</script>
