// Code Created By: Kevin Martinez
// Code Created Date: 29/08/2024
// Path: src/common/composables/example/useDeleteExample.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useExampleService } from '@/common/services/exampleService'

/**
 * Hook to delete a Example.
 * @param {Function} handleSuccess The function to call on success.
 * @param {Function} handleError The function to call on error.
 * @returns {Object} The delete Example data.
 */
export function useDeleteExample(handleSuccess, handleError) {
  const queryClient = useQueryClient()
  const { deleteExample } = useExampleService()

  /**
   * Delete a Example.
   * @param {String} id The id of the Example to delete.
   * @returns {Promise<Object>} A promise that resolves to the deleted Example data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, isSuccess, data } = useMutation({
    mutationFn: (id) => deleteExample(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['Examples'])
      toast('Example eliminado con éxito', { type: 'success' })
      if (handleSuccess) {
        handleSuccess()
      }
    },
    onError: (error) => {
      toast('Error al eliminar el Example', { type: 'error' })
      if (handleError) {
        handleError(error)
      }
    }
  })

  return {
    deleteExample: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
