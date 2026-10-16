<template>
  <div class="card p-5 sm:p-6 mt-6">
    <h2 class="section-title">Your class</h2>
    <p class="text-sm text-muted mt-1">Studying with a tutor or prep centre? Join their class with the code they gave you.</p>

    <ul v-if="centres.length" class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200">
      <li v-for="c in centres" :key="c.id" class="flex items-center justify-between gap-3 p-3">
        <div>
          <div class="font-medium">{{ c.name }}</div>
          <div v-if="c.tutor" class="text-xs text-muted">Tutor: {{ c.tutor }}</div>
        </div>
        <button class="btn btn-ghost btn-sm" @click="leave(c)">Leave</button>
      </li>
    </ul>

    <form class="mt-4" @submit.prevent="join">
      <div class="flex flex-col sm:flex-row gap-2">
        <input v-model="code" class="input sm:max-w-xs font-mono uppercase placeholder:normal-case placeholder:font-sans" placeholder="Class code, e.g. TRACT-7K2M" autocomplete="off" spellcheck="false" required />
        <button class="btn btn-secondary" :disabled="busy || !code.trim()">{{ busy ? 'Joining…' : 'Join class' }}</button>
      </div>
      <p class="text-xs text-muted mt-2">Your tutor will see your name, email, plan, test date, target band and practice scores. You can leave at any time.</p>
    </form>
    <Alert v-if="message" :tone="tone" class="mt-3">{{ message }}</Alert>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'

interface Centre {
  id: number
  name: string
  tutor: string | null
}

const centres = ref<Centre[]>([])
const code = ref('')
const busy = ref(false)
const message = ref('')
const tone = ref<'success' | 'error'>('success')

async function load() {
  centres.value = (await api.get<{ data: Centre[] }>('/api/me/centres')).data.data
}

async function join() {
  busy.value = true
  message.value = ''
  try {
    const { data } = await api.post<{ message: string }>('/api/centres/join', { code: code.value })
    tone.value = 'success'
    message.value = data.message
    code.value = ''
    await load()
  } catch (e) {
    tone.value = 'error'
    message.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function leave(c: Centre) {
  if (!window.confirm(`Leave ${c.name}? Your tutor will no longer see your scores.`)) return
  try {
    await api.delete(`/api/centres/${c.id}/leave`)
    await load()
  } catch (e) {
    tone.value = 'error'
    message.value = errorMessage(e)
  }
}

onMounted(() => load().catch(() => {}))
</script>
