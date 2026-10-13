<template>
  <footer class="bg-ink text-gray-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 grid gap-10 md:grid-cols-4">
      <div class="md:col-span-2 space-y-3">
        <div class="font-grotesk font-bold text-xl text-white">Japa<span class="text-brand">Coach</span></div>
        <p class="text-sm max-w-sm">
          AI IELTS practice for Speaking, Writing, Reading and Listening. Built in Nigeria for Nigerians preparing to study, work or settle abroad.
        </p>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3">Product</h4>
        <ul class="space-y-2 text-sm">
          <li><RouterLink to="/#modules" class="hover:text-white">Modules</RouterLink></li>
          <li><RouterLink to="/pricing" class="hover:text-white">Pricing</RouterLink></li>
          <li><RouterLink to="/#faq" class="hover:text-white">FAQ</RouterLink></li>
          <li><RouterLink to="/signup" class="hover:text-white">Take a free test</RouterLink></li>
        </ul>
      </div>
      <div>
        <h4 class="text-white font-semibold mb-3">Legal</h4>
        <ul class="space-y-2 text-sm">
          <li><RouterLink to="/terms" class="hover:text-white">Terms of use</RouterLink></li>
          <li><RouterLink to="/privacy" class="hover:text-white">Privacy policy</RouterLink></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 text-xs text-gray-400 space-y-1">
        <p>Japa Coach is not affiliated with IELTS, the British Council, IDP or Cambridge. Band scores are estimates.</p>
        <p>© {{ year }} Japa Coach.</p>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
const year = new Date().getFullYear()
</script>
