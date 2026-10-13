import { computed, onBeforeUnmount, ref } from 'vue'

/**
 * Wall-clock based countdown (robust against tab throttling).
 * `remaining` goes negative after zero unless `stopAtZero` is set.
 */
export function useCountdown(options: { stopAtZero?: boolean; onZero?: () => void } = {}) {
  const remaining = ref(0)
  const running = ref(false)
  let endsAt = 0
  let timer: ReturnType<typeof setInterval> | null = null
  let firedZero = false

  function update() {
    const r = Math.round((endsAt - Date.now()) / 1000)
    remaining.value = options.stopAtZero ? Math.max(0, r) : r
    if (r <= 0 && !firedZero) {
      firedZero = true
      options.onZero?.()
      if (options.stopAtZero) stop()
    }
  }

  function start(seconds: number) {
    stop()
    firedZero = false
    endsAt = Date.now() + seconds * 1000
    running.value = true
    update()
    timer = setInterval(update, 250)
  }

  /** Start a countdown that ends at a fixed timestamp (e.g. started_at + duration). */
  function startUntil(timestampMs: number) {
    start((timestampMs - Date.now()) / 1000)
  }

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
    running.value = false
  }

  onBeforeUnmount(stop)

  const isOver = computed(() => remaining.value <= 0)
  return { remaining, running, isOver, start, startUntil, stop }
}
