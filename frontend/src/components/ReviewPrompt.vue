<template>
  <section v-if="visible" class="card p-5 sm:p-6" :class="standalone ? '' : 'mt-10 border-brand/30 bg-brand-50/50'">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="section-title">{{ heading }}</h2>
        <p class="text-sm text-muted mt-1">{{ subheading }}</p>
      </div>
      <button v-if="!standalone && !saved" class="text-muted hover:text-ink p-1 -m-1" aria-label="Not now" @click="dismiss"><X class="w-5 h-5" /></button>
    </div>

    <Alert v-if="saved" tone="success" class="mt-4">
      Thank you! {{ form.can_publish ? 'We will review it and may feature it on our homepage.' : 'Only the Japa Coach team will see it.' }}
      <button v-if="!standalone" class="underline font-medium ml-1" @click="saved = false">Edit</button>
    </Alert>

    <form v-else class="mt-4 space-y-4" @submit.prevent="submit">
      <div class="flex gap-1" role="radiogroup" aria-label="Rating">
        <button
          v-for="n in 5"
          :key="n"
          type="button"
          role="radio"
          :aria-checked="form.rating === n"
          :aria-label="`${n} star${n > 1 ? 's' : ''}`"
          class="p-1 transition hover:scale-110"
          @click="form.rating = n"
        >
          <Star class="w-8 h-8" :class="n <= form.rating ? 'fill-accent text-accent' : 'text-gray-300'" />
        </button>
      </div>

      <template v-if="form.rating">
        <div>
          <label class="label" for="review-comment">Your review</label>
          <textarea
            id="review-comment"
            v-model="form.comment"
            rows="3"
            maxlength="600"
            class="input h-auto py-3"
            :placeholder="placeholder"
            required
          />
          <div class="text-xs text-muted text-right mt-1">{{ form.comment.length }}/600</div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="label" for="review-name">Name to show</label>
            <input id="review-name" v-model="form.display_name" class="input" maxlength="40" required />
          </div>
          <div>
            <label class="label" for="review-location">City (optional)</label>
            <input id="review-location" v-model="form.location" class="input" maxlength="40" placeholder="e.g. Lagos" />
          </div>
        </div>
        <label class="flex items-start gap-3 cursor-pointer text-sm">
          <input v-model="form.can_publish" type="checkbox" class="accent-brand w-5 h-5 mt-0.5 shrink-0" />
          <span>Japa Coach may show my review, name, city and estimated band on its website.</span>
        </label>
        <Alert v-if="error" tone="error">{{ error }}</Alert>
        <button class="btn btn-primary w-full sm:w-auto" :disabled="saving">{{ saving ? 'Sending…' : existing ? 'Update review' : 'Send review' }}</button>
      </template>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Star, X } from 'lucide-vue-next'
import Alert from '@/components/ui/Alert.vue'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Review } from '@/lib/types'

const props = defineProps<{
  /** The module the user just finished, so their band in it can be shown with the review. */
  module?: Review['module']
  /** Always shown (settings page) instead of a dismissible prompt under a report. */
  standalone?: boolean
}>()

const DISMISS_KEY = 'japa.reviewPromptDismissed'

const auth = useAuthStore()
const loaded = ref(false)
const existing = ref<Review | null>(null)
const dismissed = ref(false)
const saved = ref(false)
const saving = ref(false)
const error = ref('')
const form = reactive({
  rating: 0,
  comment: '',
  display_name: auth.user?.name.split(' ')[0] ?? '',
  location: '',
  can_publish: false,
})

// Decided once on load, so the prompt stays open while the user edits what they just sent.
const offered = ref(false)
const visible = computed(() => loaded.value && offered.value && (props.standalone || !dismissed.value))
const heading = computed(() => (props.standalone ? 'Your review' : 'How is Japa Coach helping you?'))
const subheading = computed(() =>
  props.standalone
    ? existing.value
      ? 'Edit your review any time. Changes are checked again before they appear.'
      : 'Tell other test takers what Japa Coach is like. It takes 30 seconds.'
    : 'Rate your experience. Your review helps other Nigerians preparing for IELTS.',
)
const placeholder = computed(() =>
  form.rating >= 4 ? 'What helped you most? Did your band improve?' : 'What should we do better? We read every review.',
)

onMounted(async () => {
  try {
    dismissed.value = localStorage.getItem(DISMISS_KEY) === '1'
  } catch {
    // Storage blocked: just show the prompt.
  }
  try {
    const { data } = await api.get<{ review: Review | null }>('/api/me/review')
    existing.value = data.review
    if (data.review) {
      Object.assign(form, {
        rating: data.review.rating,
        comment: data.review.comment,
        display_name: data.review.display_name,
        location: data.review.location ?? '',
        can_publish: !!data.review.can_publish,
      })
    }
  } catch {
    // Reviews are optional; never block the page.
    if (!props.standalone) return
  }
  offered.value = props.standalone || (!existing.value && !dismissed.value)
  loaded.value = true
})

function dismiss() {
  dismissed.value = true
  try {
    localStorage.setItem(DISMISS_KEY, '1')
  } catch {
    // ignore
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const { data } = await api.put<{ review: Review }>('/api/me/review', {
      ...form,
      location: form.location || null,
      module: props.module ?? existing.value?.module ?? null,
    })
    existing.value = data.review
    saved.value = true
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>
