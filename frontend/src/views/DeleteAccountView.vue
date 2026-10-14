<template>
  <article class="max-w-2xl mx-auto px-4 sm:px-6 py-14">
    <h1 class="page-title">Delete your Japa Coach account</h1>
    <p class="text-muted mt-2">This applies to the website and the Japa Coach mobile app.</p>

    <div class="card p-5 sm:p-6 mt-6">
      <h2 class="section-title">What gets deleted</h2>
      <ul class="mt-3 space-y-2 text-[15px] list-disc pl-5">
        <li>Your account, profile and login details</li>
        <li>Your speaking recordings, transcripts, essays, answers, scores and reports</li>
        <li>Your study plan and your review</li>
      </ul>
      <p class="mt-3 text-sm text-muted">
        Payment records (plan, amount, date and reference) are kept, without your name or email, as long as the law requires. Days left on a paid
        plan are lost. Deletion is immediate and cannot be undone.
      </p>
    </div>

    <Alert v-if="deleted" tone="success" class="mt-6">Your account has been deleted. Thank you for practising with Japa Coach.</Alert>

    <div v-else-if="auth.isLoggedIn" class="card p-5 sm:p-6 mt-6 border-bad/30">
      <p class="text-[15px]">
        You are logged in as <strong>{{ auth.user?.email }}</strong>.
      </p>
      <label class="label mt-4" for="confirm">Type DELETE to confirm</label>
      <input id="confirm" v-model="confirm" class="input" autocomplete="off" autocapitalize="characters" />
      <Alert v-if="error" tone="error" class="mt-4">{{ error }}</Alert>
      <button class="btn mt-4 w-full sm:w-auto bg-bad text-white hover:bg-bad/90" :disabled="confirm.trim() !== 'DELETE' || deleting" @click="remove">
        {{ deleting ? 'Deleting…' : 'Delete my account forever' }}
      </button>
    </div>

    <div v-else class="card p-5 sm:p-6 mt-6">
      <h2 class="section-title">How to delete it</h2>
      <ol class="mt-3 space-y-2 text-[15px] list-decimal pl-5">
        <li>Log in below (or in the app, go to More, then Settings, then Delete my account).</li>
        <li>Type DELETE to confirm.</li>
      </ol>
      <RouterLink :to="{ name: 'login', query: { redirect: '/delete-account' } }" class="btn btn-primary mt-5">Log in to delete my account</RouterLink>
      <p class="mt-4 text-sm text-muted">
        Can't log in? Email <a class="text-brand-dark font-semibold" href="mailto:hello@japacoach.com?subject=Delete%20my%20account">hello@japacoach.com</a>
        from your account's email address and we will delete it within 30 days.
      </p>
    </div>
  </article>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Alert from '@/components/ui/Alert.vue'
import { errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const confirm = ref('')
const deleting = ref(false)
const deleted = ref(false)
const error = ref('')

async function remove() {
  deleting.value = true
  error.value = ''
  try {
    await auth.deleteAccount()
    deleted.value = true
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    deleting.value = false
  }
}
</script>
