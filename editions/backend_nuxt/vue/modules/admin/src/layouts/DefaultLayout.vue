<template>
  <!-- Sidebar Component Start Here-->
  <sidebar-component></sidebar-component>
  <!-- Sidebar Component End Here-->
  <main class="main-content">
    <div v-show="isBanner" :class="`position-relative  ${isBanner ? 'iq-banner ' + bannerStyle : ''}`">
      <!-- Header Component Start Here -->
      <header-component></header-component>
      <!-- Header Component End Here -->
    </div>
    <!-- Main Content Start Here -->
    <main-content-component>
      <!-- Router View For Pages -->
      <router-view></router-view>
    </main-content-component>
    <!-- Main Content Start Here -->

    <!-- Footer Component Start Here -->
    <footer-component></footer-component>
    <!-- Footer Component End Here -->
  </main>
</template>

<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'

// Import custom composables
import HeaderComponent from '@/common/components/partials/HeaderComponent.vue'
import SidebarComponent from '@/common/components/partials/SidebarComponent.vue'
import MainContentComponent from '@/common/components/partials/MainContentComponent.vue'
import FooterComponent from '@/common/components/partials/FooterComponent.vue'

// ===========================
// Variables
// ===========================
const route = useRoute()
const store = useStore()
const isBanner = computed(() => route.meta.isBanner)
const bannerStyle = computed(() => store.getters['setting/header_banner'])
</script>

<style></style>
