// Code Created By: Kevin Martinez
// Code Created Date: 05/06/2024
// Path: src/common/composables/user/useShowUser.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useUsersService } from '@/common/services/usersService'

/**
 * Hook to fetch the user data.
 * @param {Number} userId The ID of the user to fetch.
 * @returns {Object} The user data.
 */
export const useShowUser = (userId) => {
  // Get the getUser function from the user service.
  const { showUser } = useUsersService()

  /**
   * Fetch the user data from the server.
   * @returns {Promise<Object>} A promise that resolves to the user data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['user', userId],
    queryFn: () => showUser(userId),
    select: (data) => data.response,
    onError: () => {
      toast('Error al obtener el usuario', { type: 'error' })
    }
  })

  return { ...queryResults }
}
