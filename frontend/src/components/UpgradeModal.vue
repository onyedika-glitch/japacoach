<template>
  <transition name="fade">
    <div
      v-if="ui.upgradeOpen"
      class="fixed inset-0 z-[60] bg-black/50 grid place-items-center p-4"
      role="dialog"
      aria-modal="true"
      @click.self="ui.closeUpgrade()"
    >
      <div class="card w-full max-w-md p-6 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-accent/15 text-accent grid place-items-center">
          <Sparkles class="w-7 h-7" />
        </div>
        <h2 class="font-grotesk text-xl font-bold mt-4">Upgrade to keep practising</h2>
        <p class="text-muted mt-2">{{ ui.upgradeMessage }}</p>
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
          <button class="btn btn-secondary flex-1" @click="ui.closeUpgrade()">Not now</button>
          <button class="btn btn-primary flex-1" @click="go">See plans</button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const router = useRouter()

function go() {
  ui.closeUpgrade()
  router.push('/billing')
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
