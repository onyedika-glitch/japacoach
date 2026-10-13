import { onBeforeUnmount, ref, shallowRef } from 'vue'

const preferredTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/aac']

export function pickMimeType(): string {
  if (typeof MediaRecorder === 'undefined') return ''
  return preferredTypes.find((t) => MediaRecorder.isTypeSupported?.(t)) ?? ''
}

export function extensionFor(mime: string): string {
  if (mime.includes('webm')) return 'webm'
  if (mime.includes('mp4') || mime.includes('aac')) return 'm4a'
  if (mime.includes('ogg')) return 'ogg'
  return 'webm'
}

export interface Recording {
  blob: Blob
  mimeType: string
  durationSeconds: number
}

/** Microphone recorder with a live input level for the mic check. */
export function useRecorder() {
  const stream = shallowRef<MediaStream | null>(null)
  const recording = ref(false)
  const elapsed = ref(0)
  const level = ref(0)
  const permission = ref<'unknown' | 'granted' | 'denied' | 'unsupported'>('unknown')

  let recorder: MediaRecorder | null = null
  let chunks: Blob[] = []
  let startedAt = 0
  let tick: ReturnType<typeof setInterval> | null = null
  let audioCtx: AudioContext | null = null
  let analyser: AnalyserNode | null = null
  let raf = 0
  let resolveStop: ((r: Recording) => void) | null = null

  async function requestMic(): Promise<boolean> {
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
      permission.value = 'unsupported'
      return false
    }
    if (stream.value) return true
    try {
      stream.value = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 },
      })
      permission.value = 'granted'
      startLevelMeter()
      return true
    } catch {
      permission.value = 'denied'
      return false
    }
  }

  function startLevelMeter() {
    if (!stream.value) return
    try {
      const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
      audioCtx = new Ctx()
      const src = audioCtx.createMediaStreamSource(stream.value)
      analyser = audioCtx.createAnalyser()
      analyser.fftSize = 512
      src.connect(analyser)
      const buf = new Uint8Array(analyser.fftSize)
      const loop = () => {
        if (!analyser) return
        analyser.getByteTimeDomainData(buf)
        let sum = 0
        for (const v of buf) sum += ((v - 128) / 128) ** 2
        level.value = Math.min(1, Math.sqrt(sum / buf.length) * 4)
        raf = requestAnimationFrame(loop)
      }
      loop()
    } catch {
      /* level meter is optional */
    }
  }

  function start() {
    if (!stream.value) throw new Error('Microphone not ready')
    const mimeType = pickMimeType()
    recorder = mimeType ? new MediaRecorder(stream.value, { mimeType, audioBitsPerSecond: 32000 }) : new MediaRecorder(stream.value)
    chunks = []
    recorder.ondataavailable = (e) => {
      if (e.data.size) chunks.push(e.data)
    }
    recorder.onstop = () => {
      const type = recorder?.mimeType || mimeType || 'audio/webm'
      const durationSeconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000))
      resolveStop?.({ blob: new Blob(chunks, { type }), mimeType: type, durationSeconds })
      resolveStop = null
    }
    void audioCtx?.resume()
    recorder.start(1000)
    startedAt = Date.now()
    elapsed.value = 0
    recording.value = true
    tick = setInterval(() => (elapsed.value = Math.floor((Date.now() - startedAt) / 1000)), 250)
  }

  function stop(): Promise<Recording> {
    return new Promise((resolve) => {
      if (!recorder || recorder.state === 'inactive') {
        resolve({ blob: new Blob(chunks), mimeType: 'audio/webm', durationSeconds: elapsed.value || 1 })
        return
      }
      resolveStop = resolve
      recorder.stop()
      recording.value = false
      if (tick) clearInterval(tick)
    })
  }

  function release() {
    if (tick) clearInterval(tick)
    cancelAnimationFrame(raf)
    analyser = null
    void audioCtx?.close().catch(() => {})
    audioCtx = null
    if (recorder && recorder.state !== 'inactive') recorder.stop()
    stream.value?.getTracks().forEach((t) => t.stop())
    stream.value = null
    recording.value = false
  }

  onBeforeUnmount(release)

  return { stream, recording, elapsed, level, permission, requestMic, start, stop, release }
}
