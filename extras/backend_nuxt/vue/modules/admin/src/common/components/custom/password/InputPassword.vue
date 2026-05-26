<template>
  <div class="d-flex align-items-center">
    <label class="form-label me-2" for="password">Contraseña:</label>
    <div v-if="props.legend" class="tooltip-container">
      <span class="tooltip">Solo escribe una contraseña si deseas cambiarla</span>
      <span class="text"><i class="bi bi-info-circle"></i></span>
    </div>
  </div>
  <div class="d-flex align-items-center">
    <div class="input-group">
      <div class="position-relative w-100">
        <input class="form-control" id="password" v-model="password" :type="showPassword ? 'text' : 'password'" placeholder="Contraseña" @input="handleInput" :required="props.required" />
        <button class="btn position-absolute top-50 translate-middle-y end-0 mb-2" type="button" @click="toggleShowPassword" style="transform: translate(0, -50%)">
          <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
        </button>
        <div class="progress mt-1" style="height: 7px">
          <div class="progress-bar" :class="passwordStrength.class" role="progressbar" :style="{ width: passwordStrength.width + '%' }" :aria-valuenow="passwordStrength.width" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <span v-show="props.error" class="text-danger small" v-if="!validate && password != ''">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          La contraseña no cumple con los requisitos minimo.
        </span>
      </div>
    </div>
    <button class="btn btn-primary ms-2 btn-custom mb-2" type="button" @click="generatePassword" data-bs-toggle="tooltip" data-bs-placement="top" title="Generar contraseña">
      <i class="bi bi-key-fill bi-lg" style="font-size: 1.5rem"></i>
    </button>
  </div>
  <div class="mt-2" v-if="password">
    <p :class="`${passwordErrors.length ? 'text-danger' : 'text-success'} mb-1 d-flex align-items-center small`">
      <i :class="`${passwordErrors.length ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'} me-2`"></i>
      Debe tener al menos 8 caracteres de longitud.
    </p>
    <p :class="`${passwordErrors.lowercase ? 'text-danger' : 'text-success'} mb-1 d-flex align-items-center small`">
      <i :class="`${passwordErrors.lowercase ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'} me-2`"></i>
      Debe tener al menos una letra minúscula.
    </p>
    <p :class="`${passwordErrors.uppercase ? 'text-danger' : 'text-success'} mb-1 d-flex align-items-center small`">
      <i :class="`${passwordErrors.uppercase ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'} me-2`"></i>
      Debe tener al menos una letra mayúscula.
    </p>
    <p :class="`${passwordErrors.number ? 'text-danger' : 'text-success'} mb-1 d-flex align-items-center small`">
      <i :class="`${passwordErrors.number ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'} me-2`"></i>
      Debe tener al menos un número.
    </p>
    <p :class="`${passwordErrors.specialChar ? 'text-danger' : 'text-success'} mb-1 d-flex align-items-center small`">
      <i :class="`${passwordErrors.specialChar ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'} me-2`"></i>
      Debe tener al menos un carácter especial.
    </p>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref, reactive, defineEmits, defineProps } from 'vue'

// ===========================
// Props and Emits
// ===========================
const props = defineProps({
  legend: {
    type: Boolean,
    default: true
  },
  required: {
    type: Boolean,
    default: true
  },
  error: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['passwordValue', 'validatePassword'])

// ===========================
// Variables
// ===========================
const password = ref('')
const showPassword = ref(false)
const passwordStrength = ref({ width: 0, class: '' })
const validate = ref(false)

const passwordErrors = reactive({
  length: false,
  lowercase: false,
  uppercase: false,
  number: false,
  specialChar: false
})

// ===========================
// Methods
// ===========================
const validatePassword = (password) => {
  passwordErrors.length = password.length < 8
  passwordErrors.lowercase = !/[a-z]/.test(password)
  passwordErrors.uppercase = !/[A-Z]/.test(password)
  passwordErrors.number = !/\d/.test(password)
  passwordErrors.specialChar = !/[@$!%*?&]/.test(password)

  validate.value = !Object.values(passwordErrors).includes(true)

  emit('validatePassword', validate.value)
  emit('passwordValue', password)
}

const checkPasswordStrength = () => {
  let strength = 0

  const lengthCriteria = new RegExp('.{8,}')
  const lowercaseCriteria = new RegExp('(?=.*[a-z])')
  const uppercaseCriteria = new RegExp('(?=.*[A-Z])')
  const digitCriteria = new RegExp('(?=.*[0-9])')
  const specialCriteria = new RegExp('(?=.*[!@#$%^&*])')

  if (lengthCriteria.test(password.value)) strength += 20
  if (lowercaseCriteria.test(password.value)) strength += 20
  if (uppercaseCriteria.test(password.value)) strength += 20
  if (digitCriteria.test(password.value)) strength += 20
  if (specialCriteria.test(password.value)) strength += 20

  let strengthClass = 'bg-danger'
  if (strength >= 80) {
    strengthClass = 'bg-success'
  } else if (strength >= 60) {
    strengthClass = 'bg-warning'
  }

  if (!password.value) {
    strength = 0
    strengthClass = ''
  }

  passwordStrength.value = {
    width: strength,
    class: strengthClass
  }
}

const generatePassword = () => {
  const length = 16
  const lowercase = 'abcdefghijklmnopqrstuvwxyz'
  const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const digits = '0123456789'
  const special = '!@#$%^&*()_+~`|}{[]:;?><,./-='
  const allChars = lowercase + uppercase + digits + special
  const getRandomChar = (charset) => charset.charAt(Math.floor(Math.random() * charset.length))
  let newPassword = [getRandomChar(lowercase), getRandomChar(uppercase), getRandomChar(digits), getRandomChar(special)]

  for (let i = newPassword.length; i < length; ++i) {
    newPassword.push(getRandomChar(allChars))
  }

  newPassword = newPassword.sort(() => Math.random() - 0.5).join('')
  password.value = newPassword

  checkPasswordStrength()
  validatePassword(password.value)
}

const handleInput = () => {
  checkPasswordStrength()
  validatePassword(password.value)
}

const toggleShowPassword = () => {
  showPassword.value = !showPassword.value
}
</script>
<style scoped>
.btn-custom {
  padding: 0.2rem 0.8rem;
}

.top-50 {
  top: 43% !important;
}

.xx-small {
  font-size: 0.8rem;
  color: red;
}

.end-0 {
  right: 8px !important;
}

.tooltip-container {
  position: relative;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
  height: 28px;
  border-radius: 6px;
  margin-left: 3px;
  font-size: 17px;
  color: #007bff;
}

.tooltip {
  --background-tooltip: #6e7681;
  /* Default background color for tooltip */
  position: absolute;
  top: -60px;
  /* Adjusted top position */
  left: 50%;
  transform: translateX(-50%);
  padding: 0.5em;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s;
  background: var(--background-tooltip);
  color: white;
  /* Text color */
  border-radius: 5px;
  width: 210px;
  height: auto;
  font-size: 13px;
  text-align: center;
}

.tooltip::before {
  position: absolute;
  content: '';
  height: 0.6em;
  width: 0.6em;
  bottom: -0.2em;
  left: 50%;
  transform: translate(-50%) rotate(45deg);
  background: var(--background-tooltip);
  /* Use the same background color as the tooltip */
}

.tooltip-container:hover .tooltip {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
}
</style>
