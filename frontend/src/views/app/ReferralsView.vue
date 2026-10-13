<template>
  <div>
    <h1 class="page-title">Refer friends</h1>
    <p class="text-muted mt-1">Everyone you know is preparing for IELTS. Help them, and earn extra full speaking mocks.</p>

    <Spinner v-if="loading" block />
    <Alert v-else-if="error" tone="error" class="mt-6">{{ error }}</Alert>

    <template v-else-if="info">
      <div class="card p-6 sm:p-8 mt-6 bg-brand-50 border-brand/20">
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
          <div class="flex gap-3"><span class="step">1</span>Share your link with friends.</div>
          <div class="flex gap-3"><span class="step">2</span>They get a bonus full speaking mock when they join.</div>
          <div class="flex gap-3"><span class="step">3</span>When they buy any plan, you get bonus full speaking mocks too.</div>
        </div>

        <label class="label mt-6">Your invite link</label>
        <div class="flex gap-2">
          <input :value="link" readonly class="input bg-white text-sm" @focus="($event.target as HTMLInputElement).select()" />
          <button class="btn btn-secondary shrink-0" @click="copy">
            <Check v-if="copied" class="w-4 h-4 text-good" /><Copy v-else class="w-4 h-4" />
            <span class="hidden sm:inline">{{ copied ? 'Copied' : 'Copy' }}</span>
          </button>
        </div>
        <div class="mt-3 flex flex-col sm:flex-row gap-3">
          <a :href="whatsappHref" target="_blank" rel="noopener" class="btn bg-[#25D366] text-white hover:brightness-95">
            <MessageCircle class="w-5 h-5" /> Share on WhatsApp
          </a>
          <button v-if="canShare" class="btn btn-secondary" @click="nativeShare"><Share2 class="w-4 h-4" /> More options</button>
        </div>
        <p class="text-xs text-muted mt-3">Your code: <span class="font-mono font-semibold text-ink">{{ info.code }}</span></p>
      </div>

      <div class="mt-6 grid grid-cols-3 gap-4">
        <div class="card p-5">
          <div class="text-sm text-muted">Friends joined</div>
          <div class="font-grotesk text-3xl font-bold">{{ info.invited_count }}</div>
        </div>
        <div class="card p-5">
          <div class="text-sm text-muted">Friends who upgraded</div>
          <div class="font-grotesk text-3xl font-bold">{{ info.rewarded_count }}</div>
        </div>
        <div class="card p-5">
          <div class="text-sm text-muted">Bonus mocks left</div>
          <div class="font-grotesk text-3xl font-bold">{{ info.bonus_speaking_mocks }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Check, Copy, MessageCircle, Share2 } from 'lucide-vue-next'
import Spinner from '@/components/ui/Spinner.vue'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'

interface ReferralInfo {
  code: string
  link: string
  invited_count: number
  rewarded_count: number
  bonus_speaking_mocks: number
}

const info = ref<ReferralInfo | null>(null)
const loading = ref(true)
const error = ref('')
const copied = ref(false)

const link = computed(() => (info.value ? `${window.location.origin}/signup?ref=${encodeURIComponent(info.value.code)}` : ''))
const message = computed(
  () => `I'm preparing for IELTS with Japa Coach, an AI examiner that gives you a band score and fixes in minutes. Try a free speaking test: ${link.value}`,
)
const whatsappHref = computed(() => `https://wa.me/?text=${encodeURIComponent(message.value)}`)
const canShare = typeof navigator !== 'undefined' && 'share' in navigator

onMounted(async () => {
  try {
    const { data } = await api.get<ReferralInfo>('/api/referrals')
    info.value = data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

async function copy() {
  try {
    await navigator.clipboard.writeText(link.value)
  } catch {
    const el = document.createElement('textarea')
    el.value = link.value
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    el.remove()
  }
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

async function nativeShare() {
  try {
    await navigator.share({ title: 'Japa Coach', text: message.value })
  } catch {
    /* user cancelled */
  }
}
</script>

<style scoped>
@reference '@/assets/main.css';
.step {
  @apply w-7 h-7 rounded-full bg-brand text-white grid place-items-center font-bold text-sm shrink-0;
}
</style>
