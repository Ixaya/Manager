// Code Created By: Kevin Martinez
// Code Created Date: 05/06/2024
// Path: src/common/composables/user/useUpdateUser.js

// Import the required libraries and hooks.
import { useMutation } from '@tanstack/vue-query'
import { useUsersService } from '@/common/services/usersService'
import { toast } from 'vue3-toastify'

/**
 * Hook to update a user.
 * @returns {Object} The user data.
 */
export function useUpdateUser() {
  const { updateUser } = useUsersService()

  /**
   * Update a user.
   * @param {Object} data The data to update the user with.
   * @returns {Promise<Object>} A promise that resolves to the updated user data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (updatedUser) => updateUser(updatedUser),
    onSuccess: () => {
      toast('Usuario actualizado con éxito', { type: 'success' })
    },
    onError: (data) => {
      toast(`${data?.response.data.message}`, { type: 'error' })
    }
  })

  return {
    updateUser: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
