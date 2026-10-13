<template>
  <component :is="layout">
    <RouterView v-slot="{ Component }">
      <component :is="Component" :key="$route.path" />
    </RouterView>
  </component>
  <UpgradeModal />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PublicLayout from '@/layouts/PublicLayout.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FocusLayout from '@/layouts/FocusLayout.vue'
import UpgradeModal from '@/components/UpgradeModal.vue'

const route = useRoute()
const layout = computed(() => {
  if (route.meta.layout === 'app') return AppLayout
  if (route.meta.layout === 'focus') return FocusLayout
  return PublicLayout
})
</script>
