<template>
  <svg class="mt-5" width="98" height="99" viewBox="0 0 98 99" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M65.1171 0.5H93.4687L97.4572 8.44811L65.2733 98.5H50.2545L65.1171 0.5ZM14.9113 0.5H42.7957L46.7842 8.44811L14.6003 98.5H-0.415726L14.9113 0.5Z" stroke="#3A57E8" />
  </svg>
  <p class="mt-4 test-text">{{ testText }}</p>
  <h6 class="">{{ testOwner }}</h6>
  <p class="mb-0 text-primary">{{ testSubtitle }}</p>
</template>

<script>
export default {
  props: {
    testText: { type: String, default: '' },
    testOwner: { type: String, default: '' },
    testSubtitle: { type: String, default: '' }
  }
}
</script>
