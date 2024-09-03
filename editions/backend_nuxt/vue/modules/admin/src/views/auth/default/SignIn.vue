<template>
  <section class="login-content">
    <b-row class="m-0 align-items-center bg-white h-100">
      <b-col md="6">
        <b-row class="justify-content-center">
          <b-col md="10">
            <b-card class="card-transparent shadow-none d-flex justify-content-center mb-0 auth-card iq-auth-form">
              <div class="d-flex justify-content-center align-items-center">
                <router-link class="navbar-brand d-flex align-items-center mb-3 text-primary" :to="{ name: 'default.dashboard' }">
                  <brand-logo :width="100" :height="100"></brand-logo>
                </router-link>
              </div>
              <h2 class="mb-2 text-center">Iniciar Sesión</h2>
              <p class="text-center">Inicie sesión para mantenerse conectado.</p>
              <form :class="`needs-validation ${valid ? 'was-validated' : ''}`" @submit.prevent="submitForm" novalidate="">
                <div class="row">
                  <div class="col-lg-12">
                    <div class="form-group">
                      <label class="form-label" for="email">Email</label>
                      <input class="form-control" id="email" v-model="form.email" type="email" aria-describedby="email" placeholder=" " required="" />
                      <span class="text-danger small" v-if="v$.email.$error">Email es obligatorio.</span>
                    </div>
                  </div>
                  <div class="col-lg-12">
                    <div class="form-group">
                      <label class="form-label" for="password">Contraseña</label>
                      <input class="form-control" id="password" v-model="form.password" type="password" aria-describedby="password" placeholder=" " required="" />
                      <span class="text-danger small" v-if="v$.password.$error">La contraseña es obligatoria.</span>
                    </div>
                  </div>
                  <div class="col-lg-12 d-flex justify-content-between">
                    <div class="form-check mb-3">
                      <input class="form-check-input" id="rememberMe" v-model="form.rememberMe" type="checkbox" />
                      <label class="form-check-label" for="customCheck1">Recuérdame</label>
                    </div>
                    <a href="/auth/reset-password">¿Has olvidado tu contraseña?</a>
                  </div>
                </div>
                <div class="d-flex justify-content-center">
                  <button class="btn btn-primary" type="submit">Iniciar sesión</button>
                </div>
                <p class="mt-3 text-center">
                  ¿No tienes una cuenta?
                  <router-link :to="{ name: 'auth.register' }" class="text-underline">Haga clic aquí para registrarte.</router-link>
                </p>
              </form>
            </b-card>
          </b-col>
        </b-row>
        <div class="sign-bg">
          <svg width="280" height="230" viewBox="0 0 431 398" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g opacity="0.05">
              <rect x="-157.085" y="193.773" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 -157.085 193.773)" fill="#3B8AFF" />
              <rect x="7.46875" y="358.327" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 7.46875 358.327)" fill="#3B8AFF" />
              <rect x="61.9355" y="138.545" width="310.286" height="77.5714" rx="38.7857" transform="rotate(45 61.9355 138.545)" fill="#3B8AFF" />
              <rect x="62.3154" y="-190.173" width="543" height="77.5714" rx="38.7857" transform="rotate(45 62.3154 -190.173)" fill="#3B8AFF" />
            </g>
          </svg>
        </div>
      </b-col>
      <div class="col-md-6 d-md-block d-none bg-primary p-0 vh-100 overflow-hidden">
        <img class="img-fluid gradient-main animated-scaleX" src="@/assets/images/auth/01.png" alt="images" loading="lazy" />
      </div>
    </b-row>
  </section>
</template>

<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref } from 'vue'
import { required, email } from '@vuelidate/validators'
import { toast } from 'vue3-toastify'
import useVuelidate from '@vuelidate/core'

// Import custom composables
import { useAuth } from '@/common/composables/auth/useAuth'

// ===========================
// Variables
// ===========================
const { mutate } = useAuth()
const valid = ref(false)
const form = ref({
  email: '',
  password: '',
  rememberMe: false
})
const rules = {
  email: { required, email },
  password: { required }
}
const v$ = useVuelidate(rules, form)

// ===========================
// Methods
// ===========================
const submitForm = async () => {
  try {
    const result = await v$.value.$validate()
    valid.value = true
    if (result) mutate(form.value)
  } catch (error) {
    if (error?.response?.data?.message) toast(error.response.data.message, { type: 'error' })
    else toast('Error desconocido', { type: 'error' })
  }
}
</script>
