import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const upgradeOpen = ref(false)
  const upgradeMessage = ref('')
  const upgradeFeature = ref<string | undefined>()

  function showUpgrade(message?: string, feature?: string) {
    upgradeMessage.value = message || "You've reached your plan's limit for this feature."
    upgradeFeature.value = feature
    upgradeOpen.value = true
  }

  function closeUpgrade() {
    upgradeOpen.value = false
  }

  return { upgradeOpen, upgradeMessage, upgradeFeature, showUpgrade, closeUpgrade }
})
