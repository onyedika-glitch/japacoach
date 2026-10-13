import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    layout?: 'public' | 'app' | 'focus'
    requiresAuth?: boolean
    guestOnly?: boolean
    requiresAdmin?: boolean
    title?: string
  }
}

const app = (meta: Record<string, unknown> = {}) => ({ layout: 'app' as const, requiresAuth: true, ...meta })
const focus = (meta: Record<string, unknown> = {}) => ({ layout: 'focus' as const, requiresAuth: true, ...meta })

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { layout: 'public' } },
  { path: '/pricing', name: 'pricing', component: () => import('@/views/PricingView.vue'), meta: { layout: 'public', title: 'Pricing' } },
  { path: '/terms', name: 'terms', component: () => import('@/views/TermsView.vue'), meta: { layout: 'public', title: 'Terms' } },
  { path: '/privacy', name: 'privacy', component: () => import('@/views/PrivacyView.vue'), meta: { layout: 'public', title: 'Privacy' } },
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { layout: 'public', guestOnly: true, title: 'Log in' } },
  { path: '/signup', name: 'signup', component: () => import('@/views/SignupView.vue'), meta: { layout: 'public', guestOnly: true, title: 'Sign up' } },

  { path: '/dashboard', name: 'dashboard', component: () => import('@/views/app/DashboardView.vue'), meta: app({ title: 'Dashboard' }) },

  { path: '/speaking', name: 'speaking', component: () => import('@/views/app/SpeakingView.vue'), meta: app({ title: 'Speaking' }) },
  { path: '/speaking/test/:id', name: 'speaking-test', component: () => import('@/views/app/SpeakingTestView.vue'), meta: focus({ title: 'Speaking test' }) },
  { path: '/speaking/report/:id', name: 'speaking-report', component: () => import('@/views/app/SpeakingReportView.vue'), meta: app({ title: 'Speaking report' }) },

  { path: '/writing', name: 'writing', component: () => import('@/views/app/WritingView.vue'), meta: app({ title: 'Writing' }) },
  { path: '/writing/task/:id', name: 'writing-task', component: () => import('@/views/app/WritingTaskView.vue'), meta: focus({ title: 'Writing task' }) },
  { path: '/writing/report/:id', name: 'writing-report', component: () => import('@/views/app/WritingReportView.vue'), meta: app({ title: 'Writing report' }) },

  { path: '/reading', name: 'reading', component: () => import('@/views/app/PracticeListView.vue'), props: { module: 'reading' }, meta: app({ title: 'Reading' }) },
  { path: '/reading/test/:attemptId', name: 'reading-test', component: () => import('@/views/app/PracticeTestView.vue'), props: { module: 'reading' }, meta: focus({ title: 'Reading test' }) },
  { path: '/listening', name: 'listening', component: () => import('@/views/app/PracticeListView.vue'), props: { module: 'listening' }, meta: app({ title: 'Listening' }) },
  { path: '/listening/test/:attemptId', name: 'listening-test', component: () => import('@/views/app/PracticeTestView.vue'), props: { module: 'listening' }, meta: focus({ title: 'Listening test' }) },
  { path: '/practice/result/:attemptId', name: 'practice-result', component: () => import('@/views/app/PracticeResultView.vue'), meta: app({ title: 'Results' }) },

  { path: '/mock', name: 'mock', component: () => import('@/views/app/MockListView.vue'), meta: app({ title: 'Mock exam' }) },
  { path: '/mock/:id', name: 'mock-exam', component: () => import('@/views/app/MockExamView.vue'), meta: app({ title: 'Mock exam' }) },

  { path: '/study-plan', name: 'study-plan', component: () => import('@/views/app/StudyPlanView.vue'), meta: app({ title: 'Study plan' }) },
  { path: '/billing', name: 'billing', component: () => import('@/views/app/BillingView.vue'), meta: app({ title: 'Billing' }) },
  { path: '/referrals', name: 'referrals', component: () => import('@/views/app/ReferralsView.vue'), meta: app({ title: 'Refer friends' }) },
  { path: '/settings', name: 'settings', component: () => import('@/views/app/SettingsView.vue'), meta: app({ title: 'Settings' }) },
  { path: '/admin', name: 'admin', component: () => import('@/views/app/AdminView.vue'), meta: app({ title: 'Admin', requiresAdmin: true }) },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { layout: 'public', title: 'Not found' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, _from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, behavior: 'smooth', top: 72 }
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.boot()

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && auth.isLoggedIn) {
    return { name: 'dashboard' }
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }
  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Japa Coach` : 'Japa Coach — AI IELTS practice for Nigerians'
})

export default router
