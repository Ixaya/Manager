<template>
  <div class="iq-navbar-header animate__animated animate__fadeIn header-h">
    <b-container class="iq-container" fluid>
      <b-row>
        <b-col md="12">
          <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <h1>{{ props.title }}</h1>
              <p>{{ props.description }}</p>
            </div>
            <div v-if="props.button">
              <router-link :to="{ name: button_link }" class="btn" :class="props.button_class">
                <i class="bi" :class="props.button_icon"></i>
                {{ props.button_text }}
              </router-link>
            </div>
          </div>
        </b-col>
      </b-row>
    </b-container>
    <div class="iq-header-img">
      <img class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header.png" alt="header" />
      <img class="theme-color-purple-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header1.png" alt="header" />
      <img class="theme-color-blue-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header2.png" alt="header" />
      <img class="theme-color-green-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header3.png" alt="header" />
      <img class="theme-color-yellow-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header4.png" alt="header" />
      <img class="theme-color-pink-img img-fluid w-100 h-100 animated-scaleX" src="@/assets/images/dashboard/top-header5.png" alt="header" />
    </div>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { defineProps } from 'vue'

// ===========================
// Props
// ===========================
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  button_text: {
    type: String,
    default: ''
  },
  button_link: {
    type: String,
    default: ''
  },
  button_icon: {
    type: String,
    default: ''
  },
  button_class: {
    type: String,
    default: 'btn-soft-light'
  },
  button: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: ''
  }
})
</script>
<style scoped>
.header-h {
  height: 160px;
}

.sidebar + .main-content .iq-navbar-header .iq-header-img {
  height: 233px !important;
}

@media (max-width: 767px) {
  .header-h {
    height: 160px;
  }
}
</style>
