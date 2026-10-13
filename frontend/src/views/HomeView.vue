<template>
  <div>
    <Hero />
    <How />
    <AI />
    <Services />
    <Products />
    <Faq />
    <section class="px-4 sm:px-6 py-20">
      <div class="max-w-4xl mx-auto text-center card p-10 md:p-14 bg-ink text-white border-none">
        <h2 class="font-figtree text-3xl sm:text-4xl font-bold">Your test date is coming. Start today.</h2>
        <p class="mt-4 text-gray-300 text-lg">Take a free speaking test and see your estimated band in minutes.</p>
        <RouterLink to="/signup" class="btn btn-primary h-14 px-8 text-lg mt-8">Take a free speaking test</RouterLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import Hero from '@/components/Hero.vue'
import How from '@/components/How.vue'
import AI from '@/components/AI.vue'
import Services from '@/components/Services.vue'
import Products from '@/components/Products.vue'
import Faq from '@/components/Faq.vue'
</script>
