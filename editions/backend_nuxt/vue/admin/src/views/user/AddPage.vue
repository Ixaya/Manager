<template>
  <div>
    <!-- Sub Header Component Start Here-->
    <sub-header :title="userData ? 'Editar usuario' : 'Crear usuario'" description=""></sub-header>
    <!-- Sub Header Component End Here-->
  </div>
  <div class="container-fluid px-2 px-md-4 mb-4">
    <form v-show="!isLoading" :class="`needs-validation ${valid ? 'was-validated' : ''}`" @submit.prevent="submitForm" novalidate="" enctype="multipart/form-data">
      <b-row>
        <b-col xl="3" lg="4">
          <b-card>
            <b-card-header class="d-flex justify-content-between">
              <div class="header-title">
                <h4 class="card-title">{{ userData ? 'Editar usuario' : 'Nuevo usuario' }}</h4>
              </div>
            </b-card-header>
            <b-card-body>
              <div class="form-group">
                <div class="profile-img-edit position-relative">
                  <img class="theme-color-default-img profile-pic rounded avatar-100" v-if="resultCropper.dataURL && resultCropper.blobURL" :src="resultCropper.blobURL" alt="profile-pic" loading="lazy" />
                  <img class="theme-color-default-img profile-pic rounded avatar-100" v-else-if="userData?.user.image.url" :src="userData.user.image.url" alt="profile-pic" loading="lazy" />
                  <img class="theme-color-default-img profile-pic rounded avatar-100" v-else src="@/assets/images/avatars/01.png" alt="profile-pic" loading="lazy" />
                  <div class="upload-icone bg-primary">
                    <svg class="upload-button" width="14" height="14" viewBox="0 0 24 24" @click="triggerFileUpload">
                      <path fill="#ffffff" d="M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z" />
                    </svg>
                    <input class="file-upload" ref="fileUpload" @change="selectFile" type="file" accept="image/png, image/jpeg, image/jpg" />
                  </div>
                </div>
                <div class="img-extension mt-3">
                  <div class="d-inline-block align-items-center">
                    <span>Sólo se permiten los formatos (</span>
                    <a href="javascript:void(0);"> .jpg</a>
                    <a href="javascript:void(0);"> .png</a>
                    <a href="javascript:void(0);"> .jpeg</a>
                    <span>).</span>
                  </div>
                </div>
              </div>
              <div v-if="userData" class="form-group">
                <p>
                  <b>ID:</b>
                  {{ userData?.user.id }}
                </p>
                <p>
                  <b>Última actualización:</b>
                  {{ userData?.user.last_update }}
                </p>
                <p>
                  <b>Dirección IP:</b>
                  {{ userData?.user.ip_address }}
                </p>
              </div>
              <div class="form-group">
                <label class="form-label">Rol:</label>
                <select v-model="form.role" class="selectpicker form-control" name="type" data-style="py-0" required="">
                  <option value="" selected disabled>Selecciona un rol</option>
                  <option v-for="item in data" :key="item.id" :value="item.id">{{ item.description }}</option>
                </select>
                <span class="text-danger small" v-if="v$.role.$error">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  Rol es obligatorio.
                </span>
              </div>
            </b-card-body>
          </b-card>
        </b-col>
        <div class="col-xl-9 col-lg-8">
          <b-card>
            <b-card-header class="d-flex justify-content-between">
              <div class="header-title">
                <h4 class="card-title">Informacion del usuario</h4>
              </div>
            </b-card-header>
            <b-card-body>
              <div class="new-user-info">
                <b-row>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="first_name">Nombre (s):</label>
                    <input class="form-control" id="first_name" v-model="form.first_name" type="text" placeholder="Nombre (s)" required="" @keypress="validateKeypress($event, 'text')" autocomplete="off" @paste.prevent @dragover.prevent />
                    <span class="text-danger small" v-if="v$.first_name.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Nombre (s) es obligatorio.
                    </span>
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="last_name">Apellido:</label>
                    <input class="form-control" id="last_name" v-model="form.last_name" type="text" placeholder="Apellido" required="" @keypress="validateKeypress($event, 'text')" autocomplete="off" @paste.prevent @dragover.prevent />
                    <span class="text-danger small" v-if="v$.last_name.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Apellido es obligatorio.
                    </span>
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="company">Compañía:</label>
                    <input class="form-control" id="company" v-model="form.company" type="text" placeholder="Compañía" autocomplete="off" />
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="phone">Teléfono:</label>
                    <input class="form-control" id="phone" v-model="form.phone" type="text" placeholder="Teléfono" @keypress="validateKeypress($event, 'number')" @input="formatPhoneNumber" autocomplete="off" />
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="email">Email:</label>
                    <input class="form-control" id="email" v-model="form.email" type="email" placeholder="Email" required="" autocomplete="off" />
                    <span class="text-danger small" v-if="v$.email.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Email es obligatorio.
                    </span>
                  </b-col>
                  <b-col class="form-group" sm="6">
                    <label class="form-label">Estatus:</label>
                    <select class="selectpicker form-control" v-model="form.status" name="status" data-style="py-0" required="">
                      <option value="" selected disabled>Selecciona un estatus</option>
                      <option value="1">Activo</option>
                      <option value="0">Inactivo</option>
                    </select>
                    <span class="text-danger small" v-if="v$.status.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Estatus es obligatorio.
                    </span>
                  </b-col>
                </b-row>
                <hr />
                <h5 class="mb-3">Seguridad</h5>
                <div class="row">
                  <b-col class="form-group" md="12">
                    <label class="form-label" for="uname">Username:</label>
                    <input class="form-control" id="username" v-model="form.username" type="text" placeholder="Username" required="" autocomplete="off" />
                    <span class="text-danger small" v-if="v$.username.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Username es obligatorio.
                    </span>
                  </b-col>
                  <b-col class="form-group" md="6">
                    <inputPassword :required="userData ? false : true" :legend="userData ? true : false" @passwordValue=";(form.password = $event), (form.confirmPassword = $event)" @validatePassword="validPassword = $event" />
                    <span class="text-danger small" v-if="v$.password.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      La contraseña es obligatoria.
                    </span>
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="confirmPassword">Repita la contraseña:</label>
                    <input class="form-control" id="confirmPassword" v-model="form.confirmPassword" type="password" placeholder="Repita la contraseña" :required="userData ? false : true" />
                    <span class="text-danger small" v-if="v$.confirmPassword.$error || !validPasswordConfirm">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Las contraseñas no coinciden.
                    </span>
                  </b-col>
                </div>
                <button class="btn btn-success" type="submit">Guardar</button>
              </div>
            </b-card-body>
          </b-card>
        </div>
      </b-row>
    </form>
    <profile-skeleton v-show="isLoading" />
  </div>
  <vue-cropper :pic="pic" :isShowModal="isShowModal" @result="handleResult" @file="handleFile" />
  <button class="d-none" type="hidden" data-bs-target="#UploadPhotoModal" data-bs-toggle="modal" ref="showModalCropper"></button>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref, watchEffect, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { toast } from 'vue3-toastify'
import { required, email, sameAs } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

// Import custom composables
import { useCreateUser } from '@/common/composables/user/useCreateUser'
import { useUpdateUser } from '@/common/composables/user/useUpdateUser'
import { useShowUser } from '@/common/composables/user/useShowUser'
import { useRoles } from '@/common/composables/role/useRoles'

// Import custom components
import vueCropper from '@/common/components/custom/cropper/VueCropper.vue'
import SubHeader from '@/common/components/custom/header/SubHeader.vue'
import inputPassword from '@/common/components/custom/password/InputPassword.vue'
import profileSkeleton from '@/common/components/custom/skeleton/ProfileSkeleton.vue'

// ===========================
// Variables
// ===========================
const router = useRouter()
const route = useRoute()
const { createUser } = useCreateUser()
const { data } = useRoles()

const resultCropper = reactive({ dataURL: '', blobURL: '' })
const userId = ref(route.params.id)
const pic = ref('')
const valid = ref(false)
const validPassword = ref(false)
const validPasswordConfirm = ref(true)
const fileUpload = ref(null)
const showModalCropper = ref(null)
const isShowModal = ref(false)
const { data: userData, isLoading } = useShowUser(userId.value)
const { updateUser } = useUpdateUser(userId.value)

// Form state
const form = reactive({
  role: '',
  first_name: '',
  last_name: '',
  image: '',
  company: '',
  phone: '',
  email: '',
  status: '',
  username: '',
  password: '',
  confirmPassword: ''
})

// ===========================
// Watchers
// ===========================
watchEffect(() => {
  if (!isLoading.value && userData.value?.user) {
    form.id = userData.value.user.id
    form.first_name = userData.value.user.first_name
    form.last_name = userData.value.user.last_name
    form.company = userData.value.user.company
    form.phone = userData.value.user.phone
    form.email = userData.value.user.email
    form.status = userData.value.user.active
    form.username = userData.value.user.username
    form.role = userData.value.user.user_groups.id
  }
})

// ===========================
// Validation
// ===========================
const rules = computed(() => {
  return {
    role: { required },
    first_name: { required },
    last_name: { required },
    email: { required, email },
    status: { required },
    username: { required, email },
    password: userData.value ? {} : { required },
    confirmPassword: {
      ...(userData.value ? {} : { required }),
      sameAs: sameAs(form.password)
    }
  }
})

const v$ = useVuelidate(rules, form, { mode: 'onBlur' })

// ===========================
// Methods
// ===========================
const triggerFileUpload = () => {
  fileUpload.value.click()
}

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

      // Create FormData object
      const formData = new FormData()
      for (const key in form) {
        formData.append(key, form[key])
      }

      // Create user
      if (userData.value) {
        await updateUser(formData)
      } else {
        await createUser(formData)
      }

      // Reset form
      v$.value.$reset()
      valid.value = false
      setTimeout(() => router.push({ name: 'default.user-list' }), 1000)
    } else {
      toast('Por favor, complete todos los campos obligatorios', { type: 'error' })
    }
  } catch (error) {
    console.error(error)
  }
}

// Cropper methods
const selectFile = (e) => {
  pic.value = ''

  const { files } = e.target
  if (!files || !files.length) return

  const file = files[0]
  const reader = new FileReader()
  reader.readAsDataURL(file)
  reader.onload = () => {
    showModalCropper.value.click()
    setTimeout(() => (pic.value = String(reader.result)), 1000)

    if (!form.image.value) return
    form.image.value.value = ''
  }
}

const handleResult = (result) => {
  resultCropper.dataURL = result.dataURL
  resultCropper.blobURL = result.blobURL
}

const handleFile = (file) => {
  form.image = file
}
// End Cropper methods

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
