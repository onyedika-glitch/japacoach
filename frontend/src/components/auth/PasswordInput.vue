<template>
  <div class="relative">
    <input
      v-bind="$attrs"
      v-model="model"
      :type="visible ? 'text' : 'password'"
      class="input pr-12"
      autocapitalize="off"
      spellcheck="false"
    />
    <button
      type="button"
      class="absolute inset-y-0 right-0 w-12 grid place-items-center text-muted hover:text-ink"
      :aria-label="visible ? 'Hide password' : 'Show password'"
      :aria-pressed="visible"
      @click="visible = !visible"
    >
      <EyeOff v-if="visible" class="w-5 h-5" />
      <Eye v-else class="w-5 h-5" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'

defineOptions({ inheritAttrs: false })

const model = defineModel<string>({ required: true })
const visible = ref(false)
</script>
