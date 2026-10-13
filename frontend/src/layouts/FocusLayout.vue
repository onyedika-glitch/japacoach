<template>
  <div class="min-h-screen flex flex-col bg-brand-50/40">
    <header class="sticky top-0 z-40 bg-white border-b border-gray-100">
      <div class="max-w-7xl mx-auto flex items-center justify-between px-4 h-14">
        <Logo to="/dashboard" />
        <button class="btn btn-ghost btn-sm" @click="exit">
          <X class="w-4 h-4" /> Exit
        </button>
      </div>
    </header>
    <main class="flex-1">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import Logo from '@/components/Logo.vue'

const router = useRouter()

function exit() {
  if (window.confirm('Leave this test? Your progress so far is saved.')) {
    router.push('/dashboard')
  }
}
</script>
