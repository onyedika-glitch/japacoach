export type TestType = 'academic' | 'general'
export type PlanId = 'free' | 'weekly' | 'monthly' | 'sprint'
export type Module = 'speaking' | 'writing' | 'reading' | 'listening'
export type PracticeModule = 'reading' | 'listening'

export interface User {
  id: number
  name: string
  email: string
  avatar_url: string | null
  is_admin: boolean
  is_tutor: boolean
  test_type: TestType
  target_band: number | null
  test_date: string | null
  email_reminders: boolean
  plan: PlanId
  plan_expires_at: string | null
  is_premium: boolean
  referral_code: string
  streak_days: number
}

export interface Usage {
  used: number
  limit: number | null
}

export interface StudyPlanTask {
  module: Module | string
  title: string
  minutes: number
}

export interface StudyPlan {
  generated_at: string
  summary: string
  weeks: { week: number; focus: string; days: { day: string; tasks: StudyPlanTask[] }[] }[]
  tips: string[]
}

export interface Dashboard {
  user: User
  usage: Record<'speaking_mocks' | 'speaking_drills' | 'writing' | 'reading' | 'listening', Usage>
  latest: Record<Module | 'overall', number | null>
  history: { date: string; module: Module; band: number }[]
  recent_attempts: { id: number; module: Module; title: string; band: number | null; status: string; created_at: string }[]
  study_plan: StudyPlan | null
  days_to_test: number | null
}

export interface CriterionScore {
  band: number
  comment: string
}

export interface Correction {
  original: string
  corrected: string
  explanation: string
}

export type SpeakingMode = 'full' | 'part1' | 'part2' | 'part3'
export type SpeakingStatus = 'in_progress' | 'processing' | 'scored' | 'failed'

export interface SpeakingAnswer {
  id: number
  status: 'uploaded' | 'transcribed' | 'failed'
  duration_seconds: number
  transcript: string | null
  audio_url: string | null
}

export interface SpeakingQuestion {
  id: number
  part: 1 | 2 | 3
  position: number
  prompt: string
  cue_points: string[] | null
  audio_url: string | null
  answer: SpeakingAnswer | null
}

export interface SpeakingFeedback {
  summary: string
  strengths: string[]
  improvements: string[]
  corrections: Correction[]
  better_answers: { question: string; answer: string }[]
  pronunciation_tips: { word: string; tip: string }[]
  metrics: {
    words_per_minute: number
    long_pauses: number
    filler_words: number
    total_speaking_seconds: number
  }
}

export interface SpeakingAttempt {
  id: number
  mode: SpeakingMode
  status: SpeakingStatus
  overall_band: number | null
  scores: Record<string, CriterionScore> | null
  feedback: SpeakingFeedback | null
  created_at: string
  scored_at: string | null
  questions?: SpeakingQuestion[]
}

export type ChartVisual = {
  type: 'bar' | 'line' | 'pie'
  title: string
  unit?: string
  labels: string[]
  datasets: { label: string; data: number[] }[]
}
export type TableVisual = { type: 'table'; title: string; columns: string[]; rows: (string | number)[][] }
export type TaskVisual = ChartVisual | TableVisual

export interface WritingTask {
  id: number
  task_number: 1 | 2
  test_type: TestType | 'both'
  title: string
  prompt: string
  visual: TaskVisual | null
  min_words: number
  recommended_minutes: number
}

export interface WritingAttempt {
  id: number
  task: WritingTask
  essay: string
  word_count: number
  status: SpeakingStatus
  overall_band: number | null
  scores: Record<string, CriterionScore> | null
  feedback: {
    summary: string
    strengths: string[]
    improvements: string[]
    corrections: Correction[]
    model_answer: string
  } | null
  created_at: string
}

export type QuestionType = 'mcq' | 'tfng' | 'ynng' | 'gap' | 'matching'

export interface PracticeQuestion {
  id: number
  number: number
  type: QuestionType
  prompt: string
  options: { key: string; text: string }[] | null
}

export interface PracticeSection {
  id: number
  position: number
  title: string
  instructions: string
  body: string | null
  audio_url: string | null
  transcript: { speaker: string; text: string }[] | null
  questions: PracticeQuestion[]
}

export interface PracticeTestSummary {
  id: number
  title: string
  test_type: TestType | 'both'
  question_count: number
  duration_minutes: number
  best_band: number | null
  attempts_count: number
}

export type AnswerValue = string | string[]

export interface PracticeAttempt {
  id: number
  module: PracticeModule
  status: 'in_progress' | 'submitted'
  started_at: string
  submitted_at: string | null
  answers: Record<string, AnswerValue>
  correct_count: number | null
  total_count: number
  band: number | null
  test: {
    id: number
    title: string
    module: PracticeModule
    duration_minutes: number
    sections: PracticeSection[]
  }
  review:
    | {
        question_id: number
        number: number
        your_answer: AnswerValue | null
        correct_answers: string[]
        is_correct: boolean
        explanation: string | null
      }[]
    | null
}

export type PartStatus = 'not_started' | 'in_progress' | 'processing' | 'done'

export interface MockExam {
  id: number
  status: 'in_progress' | 'completed'
  test_type: TestType
  overall_band: number | null
  created_at: string
  parts: {
    listening: { test_id: number; attempt_id: number | null; status: PartStatus; band: number | null }
    reading: { test_id: number; attempt_id: number | null; status: PartStatus; band: number | null }
    writing: {
      task1_id: number
      task2_id: number
      task1_attempt_id: number | null
      task2_attempt_id: number | null
      status: PartStatus
      band: number | null
    }
    speaking: { attempt_id: number | null; status: PartStatus; band: number | null }
  }
}

export interface Plan {
  id: Exclude<PlanId, 'free'>
  name: string
  price: number
  days: number
  features: string[]
  highlight: boolean
}

export interface PlansResponse {
  currency: string
  plans: Plan[]
  free_limits: Record<string, number | null>
}

export interface Payment {
  reference: string
  plan: string
  amount: number
  currency: string
  status: string
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Review {
  id: number
  rating: number
  comment: string
  display_name: string
  location: string | null
  module: 'speaking' | 'writing' | 'reading' | 'listening' | null
  band: number | null
  can_publish?: boolean
  status?: 'pending' | 'approved' | 'hidden'
}
