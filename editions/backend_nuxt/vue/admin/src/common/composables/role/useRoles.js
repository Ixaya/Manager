// Code Created By: Kevin Martinez
// Code Created Date: 05/06/2024
// Path: src/common/composables/role/useRoles.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useUsersService } from '@/common/services/usersService'

/**
 * Hook to fetch the user roles data.
 * @returns {Object} The user roles data.
 */
export const useRoles = () => {
  // Get the getRoles function from the role service.
  const { getRoles } = useUsersService()

  /**
   * Fetch the user roles data from the server.
   * @returns {Promise<Object>} A promise that resolves to the roles data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['roles'],
    queryFn: () => getRoles(),
    select: (data) => data.response,
    onError: () => {
      toast('Error al obtener los roles', { type: 'error' })
    }
  })

  return { ...queryResults }
}
