// Code Created By: Kevin Martinez
// Code Created Date: 05/06/2024
// Path: src/common/composables/users/useCreateUser.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useUsersService } from '@/common/services/usersService'
import { toast } from 'vue3-toastify'

/**
 * Hook to create a new user.
 * @returns {Object} The user data.
 */
export function useCreateUser() {
  const queryClient = useQueryClient()
  const { createUser } = useUsersService()

  /**
   * Create a new user.
   * @param {Object} data The data to create the user with.
   * @returns {Promise<Object>} A promise that resolves to the created user data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (newUser) => createUser(newUser),
    onSuccess: () => {
      queryClient.invalidateQueries(['users'])
      toast('Usuario creado con éxito', { type: 'success' })
    },
    onError: (data) => {
      toast(`${data?.response.data.message}`, { type: 'error' })
    }
  })

  return {
    createUser: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
