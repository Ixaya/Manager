// Code Created By: Kevin Martinez
// Code Created Date: 06/06/2024
// Path: src/common/composables/auth/useRegister.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useAuthService } from '@/common/services/authService'
import { toast } from 'vue3-toastify'

/**
 * Hook to register a new user.
 * @returns {Object} The user data.
 */
export function useRegister() {
  const queryClient = useQueryClient()
  const { register } = useAuthService()

  /**
   * Register a new user.
   * @param {Object} data The data to register the user with.
   * @returns {Promise<Object>} A promise that resolves to the registered user data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (newUser) => register(newUser),
    onSuccess: (data) => {
      if (data.status == -1) {
        return toast(data.message != '' ? data.message : 'Error al registrar el usuario', { type: 'error' })
      }
      queryClient.invalidateQueries(['user'])
      toast('Usuario registrado con éxito', { type: 'success' })
    },
    onError: (data) => {
      toast(`${data?.response.data.message}`, { type: 'error' })
    }
  })

  return {
    register: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
