<template>
  <a
    :href="PLAY_STORE_URL"
    target="_blank"
    rel="noopener"
    class="inline-block transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand rounded-lg"
    aria-label="Get Japa Coach on Google Play"
  >
    <!-- Official badge artwork includes its own transparent padding, per Google's badge guidelines. -->
    <img src="/google-play-badge.png" alt="Get it on Google Play" width="646" height="250" :class="sizeClass" loading="lazy" />
  </a>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.japacoach.app'

const props = withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'md' })
const sizeClass = computed(() => (props.size === 'sm' ? 'h-14 w-auto' : 'h-[4.5rem] w-auto'))
</script>
