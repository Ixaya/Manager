// Code Created By: Kevin Martinez
// Code Created Date: 04/06/2024
// Path: src/common/composables/user/useUsers.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { ref } from 'vue'
import { toast } from 'vue3-toastify'
import { useUsersService } from '@/common/services/usersService'

/**
 * Hook to fetch the users data.
 * @returns {Object} The users data.
 */
export const useUsers = () => {
  // Create a reactive reference to the query parameters.
  const params = ref({
    page: 1,
    searchQuery: '',
    limit: 10
  })
  // Get the getUsers function from the users service.
  const { getUsers } = useUsersService()

  /**
   * Fetch the users data from the server.
   * @returns {Promise<Object>} A promise that resolves to the users data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['users'],
    queryFn: () => getUsers(params.value),
    select: (data) => data.response,
    refetchOnWindowFocus: false,
    enabled: false,
    refetchOnMount: false,
    onError: () => {
      toast('Error al obtener los usuarios', { type: 'error' })
    }
  })

  return { ...queryResults, params }
}
