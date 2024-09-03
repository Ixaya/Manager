// Code Created By: Kevin Martinez
// Code Created Date: 29/08/2024
// Path: src/common/composables/examples/useShowExample.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useExampleService } from '@/common/services/exampleService'

/**
 * Hook to fetch the example data.
 * @param {Number} exampleId The ID of the example to fetch.
 * @returns {Object} The example data.
 */
export const useShowExample = (exampleId) => {
  // Get the getExample function from the example service.
  const { showExample } = useExampleService()

  /**
   * Fetch the example data from the server.
   * @returns {Promise<Object>} A promise that resolves to the example data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['example', exampleId],
    queryFn: () => showExample(exampleId),
    select: (data) => data.response,
    onError: () => {
      toast('Error al obtener el example', { type: 'error' })
    }
  })

  return { ...queryResults }
}
