// Code Created By: Kevin Martinez
// Code Created Date: 04/06/2024
// Path: src/common/composables/user/useDeleteUser.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useUsersService } from '@/common/services/usersService'

/**
 * Hook to delete a user.
 * @param {Function} handleSuccess The function to call on success.
 * @param {Function} handleError The function to call on error.
 * @returns {Object} The delete user data.
 */
export function useDeleteUser(handleSuccess, handleError) {
  const queryClient = useQueryClient()
  const { deleteUser } = useUsersService()

  /**
   * Delete a user.
   * @param {String} id The id of the user to delete.
   * @returns {Promise<Object>} A promise that resolves to the deleted user data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, isSuccess, data } = useMutation({
    mutationFn: (id) => deleteUser(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['users'])
      toast('Usuario eliminado con éxito', { type: 'success' })
      if (handleSuccess) {
        handleSuccess()
      }
    },
    onError: (error) => {
      toast('Error al eliminar el usuario', { type: 'error' })
      if (handleError) {
        handleError(error)
      }
    }
  })

  return {
    deleteUser: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
