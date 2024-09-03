// Code Created By: Kevin Martinez
// Code Created Date: 29/05/2024
// Path: src/common/composables/auth/useAuth.js

// Import the required libraries and hooks.
import { useMutation } from '@tanstack/vue-query'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import { useAuthService } from '@/common/services/authService'

/**
 * Hook to authenticate the user.
 * @returns {Object} The user authentication data.
 */
export const useAuth = () => {
  const router = useRouter()
  const store = useStore()
  const { login } = useAuthService()

  /**
   * Authenticate the user.
   * @param {Object} params The user credentials.
   * @returns {Promise<Object>} A promise that resolves to the user data.
   * @throws {Error} Throws an error if the request fails.
   */
  return useMutation({
    mutationFn: (params) => login(params),
    onError: () => {
      toast('Usuario/contraseña incorrectos', { type: 'error' })
    },
    onSuccess: (data) => {
      if (data.info?.user_groups[0] != 'admin') {
        return toast('No tienes permisos para acceder, contacta al administrador', { type: 'error' })
      }
      store.commit('login', { user: data.info, token: data.api_key })
      toast('Bienvenido! ' + data.info.full_name, { type: 'success' })
      setTimeout(() => {
        router.push({ name: 'default.dashboard' })
      }, 1000)
    }
  })
}
