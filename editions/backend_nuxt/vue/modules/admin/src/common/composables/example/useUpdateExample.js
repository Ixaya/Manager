// Code Created By: Kevin Martinez
// Code Created Date: 29/08/2024
// Path: src/common/composables/Example/useUpdateExample.js

// Import the required libraries and hooks.
import { useMutation } from '@tanstack/vue-query'
import { useExampleService } from '@/common/services/exampleService'
import { toast } from 'vue3-toastify'

/**
 * Hook to update a Example.
 * @returns {Object} The Example data.
 */
export function useUpdateExample() {
  const { updateExample } = useExampleService()

  /**
   * Update a Example.
   * @param {Object} data The data to update the Example with.
   * @returns {Promise<Object>} A promise that resolves to the updated Example data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (updatedExample) => updateExample(updatedExample),
    onSuccess: () => {
      toast('Example actualizado con éxito', { type: 'success' })
    },
    onError: (data) => {
      toast(`${data?.response.data.message}`, { type: 'error' })
    }
  })

  return {
    updateExample: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
