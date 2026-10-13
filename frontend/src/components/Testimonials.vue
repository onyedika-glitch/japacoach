<template>
  <section v-if="reviews.length" id="reviews" class="px-4 sm:px-6 py-20 md:py-28 scroll-mt-16">
    <div class="max-w-6xl mx-auto">
      <div class="text-center max-w-2xl mx-auto">
        <h4 class="text-brand-dark font-semibold">Reviews</h4>
        <h2 class="mt-3 font-figtree text-3xl sm:text-5xl font-bold tracking-[-0.02em]">What test takers say</h2>
        <p class="mt-4 text-lg text-muted">Real reviews from people practising on Japa Coach.</p>
      </div>

      <div class="mt-14 columns-1 md:columns-2 lg:columns-3 gap-5 [&>*]:mb-5">
        <figure v-for="r in reviews" :key="r.id" class="card p-6 break-inside-avoid">
          <div class="flex gap-0.5" :aria-label="`${r.rating} out of 5 stars`">
            <Star v-for="n in 5" :key="n" class="w-4 h-4" :class="n <= r.rating ? 'fill-accent text-accent' : 'text-gray-200'" />
          </div>
          <blockquote class="mt-4 text-[15px] leading-relaxed whitespace-pre-line">“{{ r.comment }}”</blockquote>
          <figcaption class="mt-5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-dark font-bold grid place-items-center shrink-0">{{ r.display_name.charAt(0).toUpperCase() }}</div>
              <div class="min-w-0">
                <div class="font-semibold truncate">{{ r.display_name }}</div>
                <div v-if="r.location" class="text-sm text-muted truncate">{{ r.location }}</div>
              </div>
            </div>
            <div v-if="r.band && r.module" class="chip bg-brand-50 text-brand-dark shrink-0">{{ moduleLabels[r.module] }} {{ r.band.toFixed(1) }}</div>
          </figcaption>
        </figure>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Star } from 'lucide-vue-next'
import { api } from '@/lib/api'
import { moduleLabels } from '@/lib/format'
import type { Review } from '@/lib/types'

const reviews = ref<Review[]>([])

onMounted(async () => {
  try {
    reviews.value = (await api.get<{ data: Review[] }>('/api/reviews')).data.data
  } catch {
    // No reviews section if the request fails.
  }
})
</script>
