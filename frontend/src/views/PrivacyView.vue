<template>
  <article class="max-w-3xl mx-auto px-4 sm:px-6 py-14 legal">
    <h1 class="page-title">Privacy policy</h1>
    <p class="text-muted">Last updated: October 2026</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>Account details:</strong> your name, email address and, if you sign in with Google, your profile picture.</li>
      <li><strong>Study profile:</strong> test type, target band and test date, which you choose to give us.</li>
      <li><strong>Your practice work:</strong> speaking recordings, transcripts, essays and answers, plus the scores and feedback we generate.</li>
      <li><strong>Payments:</strong> the plan you bought, the amount and the payment reference. Card details are handled by Bachs, never by us.</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
      <li>To mark your answers and show your progress.</li>
      <li>To build your study plan and send reminders (you can turn reminders off in settings).</li>
      <li>To process payments and prevent fraud.</li>
    </ul>

    <h2>AI processing</h2>
    <p>
      Speaking recordings are transcribed on our own servers. Transcripts and essays are sent to an AI language model provider to generate scores
      and feedback. We do not sell your data and we do not use it for advertising.
    </p>

    <h2>How long we keep it</h2>
    <p>
      <strong>Audio recordings are deleted automatically after 30 days.</strong> Transcripts, essays, scores and feedback stay in your history
      until you delete your account. Payment records are kept as long as the law requires.
    </p>

    <h2>Your choices</h2>
    <p>
      You can update your profile at any time in settings. You can delete your account and data yourself at any time on our
      <RouterLink to="/delete-account">delete account page</RouterLink> or in the mobile app (More, Settings, Delete my account). Deletion is
      immediate.
    </p>

    <h2>Cookies</h2>
    <p>We use essential cookies only: to keep you signed in and to protect forms from abuse.</p>

    <h2>Contact</h2>
    <p>Questions about your data? Reach us through the support details in your dashboard.</p>
  </article>
</template>

<style scoped>
@reference '@/assets/main.css';
.legal h2 {
  @apply font-grotesk text-xl font-semibold mt-8 mb-2;
}
.legal p {
  @apply text-[16px] leading-relaxed text-ink/90 mt-2;
}
.legal ul {
  @apply list-disc pl-6 space-y-1.5 mt-2;
}
</style>
