// Code Created By: Kevin Martinez
// Code Created Date: 29/08/2024
// Path: src/common/composables/examples/useCreateexample.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useExampleService } from '@/common/services/exampleService'
import { toast } from 'vue3-toastify'

/**
 * Hook to create a new example.
 * @returns {Object} The example data.
 */
export function useCreateExample() {
  const queryClient = useQueryClient()
  const { createExample } = useExampleService()

  /**
   * Create a new example.
   * @param {Object} data The data to create the example with.
   * @returns {Promise<Object>} A promise that resolves to the created example data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (newExample) => createExample(newExample),
    onSuccess: () => {
      queryClient.invalidateQueries(['examples'])
      toast('Example created successfully', { type: 'success' })
    },
    onError: (data) => {
      toast(`${data?.response.data.message}`, { type: 'error' })
    }
  })

  return {
    createExample: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
