// Code Created By: Kevin Martinez
// Code Created Date: 04/06/2024
// Path: src/common/composables/example/useexamples.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { ref } from 'vue'
import { toast } from 'vue3-toastify'
import { useExampleService } from '@/common/services/exampleService'

/**
 * Hook to fetch the examples data.
 * @returns {Object} The examples data.
 */
export const useExamples = () => {
  // Create a reactive reference to the query parameters.
  const params = ref({
    page: 1,
    searchQuery: '',
    limit: 10
  })
  // Get the getexamples function from the examples service.
  const { getExamples } = useExampleService()

  /**
   * Fetch the examples data from the server.
   * @returns {Promise<Object>} A promise that resolves to the examples data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['examples'],
    queryFn: () => getExamples(params.value),
    select: (data) => data.response,
    refetchOnWindowFocus: false,
    enabled: false,
    refetchOnMount: false,
    onError: () => {
      toast('Error al obtener los examples', { type: 'error' })
    }
  })

  return { ...queryResults, params }
}
