<template>
  <main class="main-content">
    <div class="position-relative">
      <header-landing></header-landing>
    </div>
    <div class="container-fluid px-2 px-md-4 mt-5 mb-5">
      <router-view></router-view>
    </div>
  </main>
  <footer>
    <Footer />
  </footer>
</template>

<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import HeaderLanding from '@/common/components/modules/landing-pages/HeaderLanding.vue'
import Footer from '@/common/components/modules/landing-pages/FooterComponent.vue'
</script>
