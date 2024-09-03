<template>
  <div>
    <!-- Sub Header Component Start Here-->
    <sub-header :title="exampleData ? 'Editar example' : 'Crear example'" description=""></sub-header>
    <!-- Sub Header Component End Here-->
  </div>
  <div class="container-fluid px-2 px-md-4 mb-4">
    <form v-show="!isLoading" :class="`needs-validation ${valid ? 'was-validated' : ''}`" @submit.prevent="submitForm" novalidate="" enctype="multipart/form-data">
      <b-row>
        <div class="col-xl-12 col-lg-12">
          <b-card>
            <b-card-header class="d-flex justify-content-between">
              <div class="header-title">
                <h4 class="card-title">Informacion del example</h4>
              </div>
            </b-card-header>
            <b-card-body>
              <div class="new-user-info">
                <b-row>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="title">Titulo:</label>
                    <input class="form-control" id="title" v-model="form.title" type="text" placeholder="Titulo" required="" autocomplete="off" @paste.prevent @dragover.prevent />
                    <span class="text-danger small" v-if="v$.title.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Titulo es obligatorio.
                    </span>
                  </b-col>
                  <b-col class="form-group" md="6">
                    <label class="form-label" for="example">Example:</label>
                    <input class="form-control" id="example" v-model="form.example" type="text" placeholder="Example" required="" autocomplete="off" @paste.prevent @dragover.prevent />
                    <span class="text-danger small" v-if="v$.example.$error">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i>
                      Example es obligatorio.
                    </span>
                  </b-col>
                </b-row>
                <hr />
                <button class="btn btn-success" type="submit">Guardar</button>
              </div>
            </b-card-body>
          </b-card>
        </div>
        <div class="col-xl-12 col-lg-12" v-show="isLoading">
          <b-card>
            <b-card-header class="d-flex justify-content-between">
              <div class="header-title">
                <Skeleton width="12rem" height="2rem"></Skeleton>
              </div>
            </b-card-header>
            <b-card-body>
              <div class="new-user-info">
                <b-row>
                  <div class="col-md-6 col-sm-12 form-group">
                    <Skeleton width="6rem"></Skeleton>
                    <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                  </div>
                  <div class="col-md-6 col-sm-12 form-group">
                    <Skeleton width="6rem"></Skeleton>
                    <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                  </div>
                </b-row>
                <hr />
                <Skeleton width="14rem" height="3rem"></Skeleton>
              </div>
            </b-card-body>
          </b-card>
        </div>
      </b-row>
    </form>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref, watchEffect, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { toast } from 'vue3-toastify'
import { required } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import Skeleton from 'primevue/skeleton'

// Import custom composables
import { useCreateExample } from '@/common/composables/example/useCreateExample'
import { useUpdateExample } from '@/common/composables/example/useUpdateExample'
import { useShowExample } from '@/common/composables/example/useShowExample'

// Import custom components
import SubHeader from '@/common/components/custom/header/SubHeader.vue'

// ===========================
// Variables
// ===========================
const router = useRouter()
const route = useRoute()
const { createExample } = useCreateExample()

const exampleId = ref(route.params.id)
const valid = ref(false)
const { data: exampleData, isLoading } = useShowExample(exampleId.value)
const { updateExample } = useUpdateExample(exampleId.value)

// Form state
const form = reactive({
  title: '',
  example: ''
})

// ===========================
// Watchers
// ===========================
watchEffect(() => {
  if (!isLoading.value && exampleData.value?.example) {
    form.id = exampleData.value.example.id
    form.title = exampleData.value.example.title
    form.example = exampleData.value.example.example
  }
})

// ===========================
// Validation
// ===========================
const rules = computed(() => {
  return {
    title: { required },
    example: { required }
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
      // Create FormData object
      const formData = new FormData()
      for (const key in form) {
        formData.append(key, form[key])
      }

      // Create Example
      if (exampleData.value) {
        await updateExample(formData)
      } else {
        await createExample(formData)
      }

      // Reset form
      v$.value.$reset()
      valid.value = false
      setTimeout(() => router.push({ name: 'default.example-list' }), 1000)
    } else {
      toast('Por favor, complete todos los campos obligatorios', { type: 'error' })
    }
  } catch (error) {
    console.error(error)
  }
}
</script>
