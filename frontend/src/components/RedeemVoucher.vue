<template>
  <form class="card p-5 mt-6" @submit.prevent="redeem">
    <div class="flex items-start gap-3">
      <Ticket class="w-6 h-6 text-brand shrink-0 mt-0.5" />
      <div class="flex-1">
        <h2 class="font-semibold">Have a voucher code?</h2>
        <p class="text-sm text-muted">Got a code from your tutor or prep centre? Enter it to activate your plan.</p>
        <div class="mt-3 flex flex-col sm:flex-row gap-2">
          <input v-model="code" class="input sm:max-w-xs font-mono uppercase" placeholder="JC-XXXX-XXXX" autocomplete="off" spellcheck="false" required />
          <button class="btn btn-primary" :disabled="busy || !code.trim()">{{ busy ? 'Checking…' : 'Redeem' }}</button>
        </div>
        <Alert v-if="message" :tone="tone" class="mt-3">{{ message }}</Alert>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Ticket } from 'lucide-vue-next'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'
import type { User } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const code = ref('')
const busy = ref(false)
const message = ref('')
const tone = ref<'success' | 'error'>('success')

async function redeem() {
  busy.value = true
  message.value = ''
  try {
    const { data } = await api.post<{ message: string; user: User }>('/api/vouchers/redeem', { code: code.value })
    auth.setUser(data.user)
    tone.value = 'success'
    message.value = data.message
    code.value = ''
  } catch (e) {
    tone.value = 'error'
    message.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>
