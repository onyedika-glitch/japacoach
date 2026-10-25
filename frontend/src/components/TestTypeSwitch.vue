<template>
  <div class="inline-flex rounded-xl bg-gray-100 p-1" role="radiogroup" aria-label="IELTS test type">
    <button
      v-for="t in types"
      :key="t.value"
      type="button"
      role="radio"
      :aria-checked="current === t.value"
      class="px-4 h-10 rounded-lg text-sm font-semibold transition"
      :class="current === t.value ? 'bg-white shadow text-ink' : 'text-muted hover:text-ink'"
      :disabled="busy"
      @click="choose(t.value)"
    >
      {{ t.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { TestType } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

/** Switches the user's IELTS type (Academic or General Training), which decides the Writing Task 1 and Reading papers they get. */
const emit = defineEmits<{ changed: [type: TestType] }>()
const auth = useAuthStore()
const busy = ref(false)
const types: { value: TestType; label: string }[] = [
  { value: 'academic', label: 'Academic' },
  { value: 'general', label: 'General Training' },
]
const current = computed(() => auth.user?.test_type ?? 'academic')

async function choose(type: TestType) {
  if (type === current.value || busy.value) return
  busy.value = true
  try {
    await auth.update({ test_type: type })
    emit('changed', type)
  } finally {
    busy.value = false
  }
}
</script>
