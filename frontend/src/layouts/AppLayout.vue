<template>
  <div class="min-h-screen bg-gray-50/60 lg:pl-64">
    <!-- Desktop sidebar -->
    <aside class="hidden lg:flex fixed inset-y-0 left-0 w-64 flex-col bg-white border-r border-gray-100 px-4 py-5">
      <Logo to="/dashboard" class="px-2" />
      <nav class="mt-8 flex-1 space-y-1">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-medium text-muted hover:bg-gray-50 hover:text-ink"
          active-class="!bg-brand-50 !text-brand-dark"
        >
          <component :is="item.icon" class="w-5 h-5" />
          {{ item.label }}
        </RouterLink>
      </nav>
      <PlanBox />
      <div class="mt-4 flex items-center gap-3 px-2">
        <Avatar />
        <div class="min-w-0 flex-1">
          <div class="text-sm font-semibold truncate">{{ auth.user?.name }}</div>
          <button class="text-xs text-muted hover:text-ink" @click="logout">Log out</button>
        </div>
      </div>
    </aside>

    <!-- Mobile top bar -->
    <header class="lg:hidden sticky top-0 z-30 bg-white border-b border-gray-100">
      <div class="flex items-center justify-between px-4 h-14">
        <Logo to="/dashboard" />
        <button class="p-2 -mr-2" aria-label="Menu" @click="menuOpen = true">
          <Menu class="w-6 h-6" />
        </button>
      </div>
    </header>

    <!-- Mobile drawer -->
    <transition name="fade">
      <div v-if="menuOpen" class="lg:hidden fixed inset-0 z-50 bg-black/40" @click.self="menuOpen = false">
        <div class="ml-auto h-full w-72 bg-white p-5 flex flex-col" @click="onDrawerClick">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <Avatar />
              <div class="text-sm font-semibold">{{ auth.user?.name }}</div>
            </div>
            <button class="p-1" aria-label="Close" @click="menuOpen = false"><X class="w-6 h-6" /></button>
          </div>
          <nav class="mt-6 flex-1 space-y-1 overflow-y-auto">
            <RouterLink
              v-for="item in [...nav, ...extraNav]"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-xl px-3 py-3 font-medium text-ink"
              active-class="bg-brand-50 text-brand-dark"
            >
              <component :is="item.icon" class="w-5 h-5" /> {{ item.label }}
            </RouterLink>
          </nav>
          <PlanBox />
          <button class="btn btn-secondary mt-3" @click="logout"><LogOut class="w-4 h-4" /> Log out</button>
        </div>
      </div>
    </transition>

    <main class="px-4 sm:px-6 lg:px-10 py-6 pb-28 lg:pb-10 max-w-6xl mx-auto">
      <slot />
    </main>

    <!-- Mobile bottom nav -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-gray-100 grid grid-cols-5 pb-[env(safe-area-inset-bottom)]">
      <RouterLink
        v-for="item in bottomNav"
        :key="item.to"
        :to="item.to"
        class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-muted"
        active-class="!text-brand-dark"
      >
        <component :is="item.icon" class="w-5 h-5" />
        {{ item.short ?? item.label }}
      </RouterLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { computed, h, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  BookOpen,
  CalendarCheck,
  ClipboardList,
  CreditCard,
  Gift,
  Headphones,
  LayoutDashboard,
  LogOut,
  Menu,
  Mic,
  PenLine,
  Settings,
  X,
} from 'lucide-vue-next'
import Logo from '@/components/Logo.vue'
import { useAuthStore } from '@/stores/auth'
import { planLabel, date } from '@/lib/format'

const auth = useAuthStore()
const router = useRouter()
const menuOpen = ref(false)

const nav = [
  { to: '/dashboard', label: 'Dashboard', short: 'Home', icon: LayoutDashboard },
  { to: '/speaking', label: 'Speaking', icon: Mic },
  { to: '/writing', label: 'Writing', icon: PenLine },
  { to: '/reading', label: 'Reading', icon: BookOpen },
  { to: '/listening', label: 'Listening', icon: Headphones },
  { to: '/mock', label: 'Full mock exam', short: 'Mock', icon: ClipboardList },
  { to: '/study-plan', label: 'Study plan', icon: CalendarCheck },
]
const extraNav = computed(() => [
  { to: '/billing', label: 'Billing', icon: CreditCard },
  { to: '/referrals', label: 'Refer friends', icon: Gift },
  { to: '/settings', label: 'Settings', icon: Settings },
])
const bottomNav = nav.slice(0, 5)

function onDrawerClick(e: MouseEvent) {
  if ((e.target as HTMLElement).closest('a')) menuOpen.value = false
}

async function logout() {
  menuOpen.value = false
  await auth.logout()
  router.push('/')
}

const Avatar = () =>
  auth.user?.avatar_url
    ? h('img', { src: auth.user.avatar_url, alt: '', class: 'w-9 h-9 rounded-full object-cover', referrerpolicy: 'no-referrer' })
    : h(
        'div',
        { class: 'w-9 h-9 rounded-full bg-brand-100 text-brand-dark grid place-items-center font-bold' },
        (auth.user?.name ?? '?').charAt(0).toUpperCase(),
      )

const PlanBox = () => {
  const u = auth.user
  if (!u) return null
  return h('div', { class: 'rounded-xl bg-brand-50 p-3 text-sm' }, [
    h('div', { class: 'flex items-center justify-between' }, [
      h('span', { class: 'font-semibold' }, `${planLabel(u.plan)} plan`),
      u.is_premium ? h('span', { class: 'chip bg-brand text-white' }, 'Premium') : null,
    ]),
    u.is_premium && u.plan_expires_at
      ? h('div', { class: 'text-xs text-muted mt-1' }, `Renews/ends ${date(u.plan_expires_at)}`)
      : h(
          'a',
          {
            href: '/billing',
            class: 'text-xs font-semibold text-brand-dark mt-1 inline-block',
            onClick: (e: MouseEvent) => {
              e.preventDefault()
              menuOpen.value = false
              router.push('/billing')
            },
          },
          'Upgrade for full mocks →',
        ),
  ])
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
