<template>
  <!-- v-html is safe here: our own content files, rendered by renderMarkdown, which escapes everything else -->
  <div class="legal-body" @click="followInternalLink" v-html="html" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { renderMarkdown } from '@/lib/markdown'

/** Body of a legal page from api/resources/content/*.md (the same file serves the page's markdown version). */
const props = defineProps<{ source: string }>()
const router = useRouter()
const html = computed(() => renderMarkdown(props.source))

/** Keep internal links inside the single-page app instead of reloading. */
function followInternalLink(event: MouseEvent) {
  const link = (event.target as HTMLElement).closest('a')
  const href = link?.getAttribute('href')
  if (href?.startsWith('/')) {
    event.preventDefault()
    router.push(href)
  }
}
</script>

<style scoped>
@reference '@/assets/main.css';
.legal-body :deep(h2) {
  @apply font-grotesk text-xl font-semibold mt-8 mb-2;
}
.legal-body :deep(p) {
  @apply text-[16px] leading-relaxed text-ink/90 mt-2;
}
.legal-body :deep(ul) {
  @apply list-disc pl-6 space-y-1.5 mt-2;
}
</style>
