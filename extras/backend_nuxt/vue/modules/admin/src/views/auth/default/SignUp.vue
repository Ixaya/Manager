<template>
  <section class="login-content">
    <div class="row m-0 align-items-center bg-white vh-100">
      <div class="col-md-6 d-md-block d-none bg-primary p-0 vh-100 overflow-hidden">
        <img class="img-fluid gradient-main animated-scaleX" src="@/assets/images/auth/05.png" alt="images" loading="lazy" />
      </div>
      <div class="col-md-6">
        <div class="row justify-content-center">
          <div class="col-md-10">
            <div class="card card-transparent shadow-none d-flex justify-content-center mb-0">
              <div class="card-body">
                <div class="d-flex justify-content-center align-items-center">
                  <router-link class="navbar-brand d-flex align-items-center mb-3 text-primary" :to="{ name: 'default.dashboard' }">
                    <brand-logo width="100" height="100"></brand-logo>
                    <!-- <h4 class="logo-title ms-3 mb-0" data-setting="app_name"><brand-name></brand-name></h4> -->
                  </router-link>
                </div>
                <h2 class="mb-2 text-center">Registrarse</h2>
                <p class="text-center">
                  Crea tu cuenta de
                  <brand-name></brand-name>.
                </p>
                <form :class="`needs-validation ${valid ? 'was-validated' : ''}`" @submit.prevent="submitForm" novalidate="" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label class="form-label" for="first_name">Nombre (s):</label>
                        <input class="form-control" id="first_name" v-model="form.first_name" type="text" placeholder="Nombre (s)" required="" @keypress="validateKeypress($event, 'text')" autocomplete="off" @paste.prevent @dragover.prevent />
                        <span class="text-danger small" v-if="v$.first_name.$error">
                          <i class="bi bi-exclamation-triangle-fill me-2"></i>
                          Nombre (s) es obligatorio.
                        </span>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label class="form-label" for="last_name">Apellido:</label>
                        <input class="form-control" id="last_name" v-model="form.last_name" type="text" placeholder="Apellido" required="" @keypress="validateKeypress($event, 'text')" autocomplete="off" @paste.prevent @dragover.prevent />
                        <span class="text-danger small" v-if="v$.last_name.$error">
                          <i class="bi bi-exclamation-triangle-fill me-2"></i>
                          Apellido es obligatorio.
                        </span>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label class="form-label" for="email">Email:</label>
                        <input class="form-control" id="email" v-model="form.email" type="email" placeholder="Email" required="" autocomplete="off" />
                        <span class="text-danger small" v-if="v$.email.$error">
                          <i class="bi bi-exclamation-triangle-fill me-2"></i>
                          Email es obligatorio.
                        </span>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label class="form-label" for="phone">Teléfono:</label>
                        <input class="form-control" id="phone" v-model="form.phone" type="text" placeholder="Teléfono" @keypress="validateKeypress($event, 'number')" @input="formatPhoneNumber" autocomplete="off" />
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-group">
                        <inputPassword :error="false" :legend="false" :required="true" @passwordValue=";(form.password = $event), (form.confirmPassword = $event)" @validatePassword="validPassword = $event" />
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label class="form-label" for="confirmPassword">Confirmar contraseña:</label>
                        <input class="form-control" id="confirmPassword" v-model="form.confirmPassword" type="password" placeholder="Repita la contraseña" required="" />
                        <span class="text-danger small" v-if="v$.confirmPassword.$error || !validPasswordConfirm">
                          <i class="bi bi-exclamation-triangle-fill me-2"></i>
                          Las contraseñas no coinciden.
                        </span>
                      </div>
                    </div>
                    <div class="col-lg-12 d-flex justify-content-center">
                      <div class="form-check mb-3">
                        <input v-model="form.terms_accepted" class="form-check-input" id="customCheck1" type="checkbox" required="" />
                        <label class="form-check-label" for="customCheck1"> He leído y acepto los <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#termsModal">&nbsp;términos y condiciones</button>.</label>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-center">
                    <button class="btn btn-primary" type="submit">Registrarse</button>
                  </div>
                  <p class="mt-3 text-center">
                    Ya tienes una cuenta
                    <router-link :to="{ name: 'auth.login' }" class="text-underline">Iniciar sesión</router-link>
                  </p>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="sign-bg sign-bg-right">
          <svg width="280" height="230" viewBox="0 0 421 359" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g opacity="0.05">
              <rect x="-15.0845" y="154.773" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 -15.0845 154.773)" fill="#3A57E8" />
              <rect x="149.47" y="319.328" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 149.47 319.328)" fill="#3A57E8" />
              <rect x="203.936" y="99.543" width="310.286" height="77.5714" rx="38.7857" transform="rotate(45 203.936 99.543)" fill="#3A57E8" />
              <rect x="204.316" y="-229.172" width="543" height="77.5714" rx="38.7857" transform="rotate(45 204.316 -229.172)" fill="#3A57E8" />
            </g>
          </svg>
        </div>
      </div>
    </div>
  </section>
  <termsModal />
</template>

<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref, reactive, computed } from 'vue'
import { required, email, sameAs } from '@vuelidate/validators'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import useVuelidate from '@vuelidate/core'

// Import custom composables
import { useRegister } from '@/common/composables/auth/useRegister'

// Import custom components
import inputPassword from '@/common/components/custom/password/InputPassword.vue'
import termsModal from '@/common/components/custom/modal/TermsModal.vue'

// ===========================
// Variables
// ===========================
const { data, register } = useRegister()
const router = useRouter()
const valid = ref(false)
const validPassword = ref(false)
const validPasswordConfirm = ref(true)

// Form state
const form = reactive({
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  password: '',
  confirmPassword: '',
  terms_accepted: false
})

// ===========================
// Validation
// ===========================
const rules = computed(() => {
  return {
    first_name: { required },
    last_name: { required },
    email: { required, email },
    password: { required },
    confirmPassword: { required, sameAs: sameAs(form.password) },
    terms_accepted: { required }
  }
})

const v$ = useVuelidate(rules, form, { mode: 'onBlur' })

// ===========================
// Methods
// ===========================
// Form submission
const submitForm = async () => {
  try {
    const result = await v$.value.$validate()
    valid.value = true

    if (result) {
      if (form.password && !validPassword.value) {
        toast('La contraseña debe cumplir con los requisitos mínimos', { type: 'error' })
        return
      }

      if (form.password !== form.confirmPassword) {
        toast('Las contraseñas no coinciden', { type: 'error' })
        validPasswordConfirm.value = false
        return
      }

      if (!form.terms_accepted) {
        toast('Debe aceptar los términos y condiciones', { type: 'error' })
        return
      }

      // Create FormData object
      const formData = new FormData()
      for (const key in form) {
        formData.append(key, form[key])
      }

      // Register user
      await register(formData)

      if (data.value?.status == -1) {
        return
      }
      // Reset form
      v$.value.$reset()
      valid.value = false
      setTimeout(() => router.push({ name: 'auth.login' }), 1000)
    } else {
      toast('Por favor, complete todos los campos obligatorios', { type: 'error' })
    }
  } catch (error) {
    const errorMessage = error?.response?.data?.message || 'Error al actualizar el perfil'
    console.log(errorMessage)
    //toast(errorMessage, { type: 'error' })
  }
}

// Input validations
const validateKeypress = (event, type) => {
  if (type === 'text' && !/[a-zA-ZáéíóúÁÉÍÓÚ\s]/.test(event.key)) {
    event.preventDefault()
  } else if (type === 'number' && !/\d/.test(event.key)) {
    event.preventDefault()
  }
}

const formatPhoneNumber = () => {
  let digits = form.phone.replace(/\D/g, '')
  digits = digits.slice(0, 10)

  if (digits.length === 10) {
    form.phone = digits.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3')
  }
}
// End Input validations
</script>

<style lang="scss" scoped>
.form-check-input {
  margin-top: 0;
}
.form-check-label {
  display: flex;
  align-items: center;
}
button.btn-link {
  padding: 0;
  margin: 0;
  font-size: inherit;
  line-height: inherit;
}
button.btn-link.align-baseline {
  align-self: baseline;
}
</style>
