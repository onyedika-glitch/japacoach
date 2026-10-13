<template>
  <div :id="`q-${question.id}`" class="scroll-mt-24">
    <div class="flex gap-3">
      <span class="shrink-0 w-8 h-8 rounded-lg bg-brand-50 text-brand-dark grid place-items-center font-bold text-sm">{{ question.number }}</span>
      <div class="flex-1 min-w-0">
        <p class="text-[15px] leading-relaxed whitespace-pre-line">{{ question.prompt }}</p>

        <!-- Gap fill -->
        <input
          v-if="question.type === 'gap'"
          :value="typeof modelValue === 'string' ? modelValue : ''"
          class="input mt-2 h-11 max-w-sm"
          :disabled="disabled"
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
          placeholder="Type your answer"
          @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />

        <!-- Matching: dropdown -->
        <select
          v-else-if="question.type === 'matching'"
          :value="typeof modelValue === 'string' ? modelValue : ''"
          class="input mt-2 h-11 max-w-sm"
          :disabled="disabled"
          @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">Choose…</option>
          <option v-for="o in options" :key="o.key" :value="o.key">{{ o.key }}. {{ o.text }}</option>
        </select>

        <!-- MCQ / TFNG / YNNG: radio buttons -->
        <div v-else class="mt-2 grid gap-2" :class="compact ? 'sm:grid-cols-3' : ''">
          <label
            v-for="o in options"
            :key="o.key"
            class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 text-[15px] cursor-pointer transition"
            :class="modelValue === o.key ? 'border-brand bg-brand-50' : 'border-gray-200 hover:border-gray-300'"
          >
            <input
              type="radio"
              class="accent-brand mt-1"
              :name="`q${question.id}`"
              :value="o.key"
              :checked="modelValue === o.key"
              :disabled="disabled"
              @change="emit('update:modelValue', o.key)"
            />
            <span><strong v-if="!compact" class="mr-1">{{ o.key }}.</strong>{{ o.text }}</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AnswerValue, PracticeQuestion } from '@/lib/types'

const props = defineProps<{ question: PracticeQuestion; modelValue: AnswerValue | undefined; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const defaults: Record<string, { key: string; text: string }[]> = {
  tfng: [
    { key: 'TRUE', text: 'True' },
    { key: 'FALSE', text: 'False' },
    { key: 'NOT GIVEN', text: 'Not given' },
  ],
  ynng: [
    { key: 'YES', text: 'Yes' },
    { key: 'NO', text: 'No' },
    { key: 'NOT GIVEN', text: 'Not given' },
  ],
}

const options = computed(() => props.question.options ?? defaults[props.question.type] ?? [])
const compact = computed(() => props.question.type === 'tfng' || props.question.type === 'ynng')
</script>
