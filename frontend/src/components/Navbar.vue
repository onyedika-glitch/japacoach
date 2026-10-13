<template>
  <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-gray-100">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-4 sm:px-6 h-16">
      <Logo />

      <nav class="hidden md:flex items-center gap-7 text-[15px] font-medium text-muted">
        <RouterLink to="/#how" class="hover:text-ink">How it works</RouterLink>
        <RouterLink to="/#modules" class="hover:text-ink">Modules</RouterLink>
        <RouterLink to="/pricing" class="hover:text-ink">Pricing</RouterLink>
        <RouterLink to="/#faq" class="hover:text-ink">FAQ</RouterLink>
      </nav>

      <div class="hidden md:flex items-center gap-3">
        <template v-if="auth.isLoggedIn">
          <RouterLink to="/dashboard" class="btn btn-primary btn-sm">Go to dashboard</RouterLink>
        </template>
        <template v-else>
          <RouterLink to="/login" class="btn btn-ghost btn-sm">Log in</RouterLink>
          <RouterLink to="/signup" class="btn btn-primary btn-sm">Start free</RouterLink>
        </template>
      </div>

      <button class="md:hidden p-2 -mr-2" :aria-expanded="open" aria-label="Menu" @click="open = !open">
        <X v-if="open" class="w-7 h-7" />
        <Menu v-else class="w-7 h-7" />
      </button>
    </div>

    <transition name="fade">
      <div v-if="open" class="md:hidden border-t border-gray-100 bg-white px-4 pb-5 pt-2 flex flex-col gap-1" @click="open = false">
        <RouterLink to="/#how" class="py-3 font-medium">How it works</RouterLink>
        <RouterLink to="/#modules" class="py-3 font-medium">Modules</RouterLink>
        <RouterLink to="/pricing" class="py-3 font-medium">Pricing</RouterLink>
        <RouterLink to="/#faq" class="py-3 font-medium">FAQ</RouterLink>
        <div class="flex gap-3 pt-3">
          <template v-if="auth.isLoggedIn">
            <RouterLink to="/dashboard" class="btn btn-primary flex-1">Dashboard</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/login" class="btn btn-secondary flex-1">Log in</RouterLink>
            <RouterLink to="/signup" class="btn btn-primary flex-1">Start free</RouterLink>
          </template>
        </div>
      </div>
    </transition>
  </header>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Menu, X } from 'lucide-vue-next'
import Logo from '@/components/Logo.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const open = ref(false)
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
