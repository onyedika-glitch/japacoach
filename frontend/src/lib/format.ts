export function money(amount: number, currency = 'NGN'): string {
  const symbol = currency === 'NGN' ? '₦' : currency === 'USD' ? '$' : `${currency} `
  return symbol + Number(amount).toLocaleString('en-NG', { maximumFractionDigits: 2 })
}

export function band(value: number | null | undefined): string {
  if (value === null || value === undefined) return 'N/A'
  return Number(value).toFixed(1)
}

export function date(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }): string {
  if (!value) return 'N/A'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', opts)
}

export function dateTime(value: string | null | undefined): string {
  return date(value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

export function clock(totalSeconds: number): string {
  const neg = totalSeconds < 0
  const s = Math.abs(Math.round(totalSeconds))
  const m = Math.floor(s / 60)
  const r = s % 60
  return `${neg ? '-' : ''}${m}:${r.toString().padStart(2, '0')}`
}

export function wordCount(text: string): number {
  const t = text.trim()
  return t ? t.split(/\s+/).length : 0
}

export const moduleLabels: Record<string, string> = {
  speaking: 'Speaking',
  writing: 'Writing',
  reading: 'Reading',
  listening: 'Listening',
  overall: 'Overall',
}

export const criterionLabels: Record<string, string> = {
  fluency_coherence: 'Fluency & Coherence',
  lexical_resource: 'Lexical Resource',
  grammatical_range_accuracy: 'Grammatical Range & Accuracy',
  pronunciation: 'Pronunciation',
  task_achievement: 'Task Achievement',
  task_response: 'Task Response',
  coherence_cohesion: 'Coherence & Cohesion',
}

export function planLabel(plan: string): string {
  return { free: 'Free', weekly: 'Weekly', monthly: 'Monthly', sprint: 'Exam Sprint' }[plan] ?? plan
}
