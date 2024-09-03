<template>
  <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar header-hover-menu">
    <div class="container-fluid navbar-inner mt-2">
      <div class="d-flex align-items-center justify-content-between w-100 landing-header">
        <router-link to="auth/login" class="active exact-active navbar-brand m-0 d-xl-flex d-none">
          <brand-logo :width="50" :height="50"></brand-logo>
          <h5 class="logo-title ms-2 my-0">{{ appName }}</h5>
        </router-link>
        <div class="d-flex align-items-center d-xl-none">
          <button data-bs-toggle="offcanvas" data-bs-target="#navbar_main" class="d-xl-none btn btn-primary rounded-pill p-1 pt-0" type="button">
            <svg width="20px" class="icon-20" viewBox="0 0 24 24">
              <path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z"></path>
            </svg>
          </button>
          <router-link to="auth/login" class="navbar-brand ms-3">
            <brand-logo :width="50" :height="50"></brand-logo>
            <h5 class="logo-title ms-2 my-0">{{ appName }}</h5>
          </router-link>
        </div>
        <!-- Horizontal Menu Start -->
        <nav id="navbar_main" class="mobile-offcanvas nav navbar navbar-expand-xl hover-nav horizontal-nav" ref="sidebarRef">
          <div class="container-fluid p-lg-0">
            <div class="offcanvas-header px-0">
              <div class="navbar-brand ms-3">
                <brand-logo :color="true" />
                <h5 class="logo-title">{{ appName }}</h5>
              </div>
              <button class="btn-close float-end px-3" @click="closeSidebar"></button>
            </div>
            <ul v-if="token == null" class="navbar-nav iq-nav-menu list-unstyled d-flex align-items-center">
              <li class="nav-item">
                <router-link class="nav-link btn btn-outline-primary me-2" :to="{ name: 'auth.login' }">Iniciar Sesión</router-link>
              </li>
              <li class="nav-item">
                <router-link class="nav-link btn btn-primary" :to="{ name: 'auth.register' }">Regístrate</router-link>
              </li>
            </ul>
          </div>
          <!-- container-fluid.// -->
        </nav>
        <!-- Sidebar Menu End -->
      </div>
    </div>
  </nav>
</template>

<script setup>
import BrandLogo from '@/common/components/custom/logo/BrandLogo.vue'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'

const store = useStore()
const appName = computed(() => store.getters['setting/app_name'])
const sidebarRef = ref(null)
const closeSidebar = () => {
  if (sidebarRef.value) {
    sidebarRef.value.classList.remove('show')
  }
}
const token = localStorage.getItem('token')
</script>
<style>
body {
  padding-right: 0 !important;
}

.logo-title {
  display: flex;
  align-items: center;
  margin: 0;
  margin-top: 0.5rem;
}

.navbar-nav .nav-item .nav-link.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
}

.navbar-nav .nav-item .nav-link.btn-primary {
  background-color: #007bff;
  border-color: #007bff;
  color: #fff;
}

.navbar-nav .nav-item .nav-link.btn-outline-primary {
  border-color: #007bff;
  color: #007bff;
}

.navbar-nav .nav-item .nav-link.btn:hover {
  text-decoration: none;
}

.navbar-nav .nav-item {
  margin-bottom: 0.5rem; /* Espacio entre los elementos del menú en dispositivos móviles */
}

@media (min-width: 992px) {
  .navbar-nav .nav-item {
    margin-bottom: 0; /* Elimina el margen en dispositivos grandes */
  }
}
</style>
