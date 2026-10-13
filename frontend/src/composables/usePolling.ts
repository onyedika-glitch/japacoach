import { onBeforeUnmount } from 'vue'

/**
 * Repeatedly runs `fn` every `intervalMs` while `shouldContinue()` returns true.
 * Stops automatically when the component unmounts.
 */
export function usePolling(fn: () => Promise<void>, shouldContinue: () => boolean, intervalMs = 5000) {
  let timer: ReturnType<typeof setTimeout> | null = null
  let stopped = false

  async function tick() {
    if (stopped || !shouldContinue()) return
    try {
      await fn()
    } catch {
      /* transient errors: keep polling */
    }
    if (!stopped && shouldContinue()) {
      timer = setTimeout(tick, intervalMs)
    }
  }

  function start() {
    stopped = false
    if (timer) clearTimeout(timer)
    timer = setTimeout(tick, intervalMs)
  }

  function stop() {
    stopped = true
    if (timer) clearTimeout(timer)
    timer = null
  }

  onBeforeUnmount(stop)
  return { start, stop }
}
