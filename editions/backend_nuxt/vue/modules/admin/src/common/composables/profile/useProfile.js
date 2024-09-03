// Code Created By: Kevin Martinez
// Code Created Date: 29/05/2024
// Path: src/common/composables/profile/useProfile.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useProfileService } from '@/common/services/profileService'

/**
 * Hook to fetch the user profile data.
 * @returns {Object} The user profile data.
 */
export const useProfile = () => {
  // Get the getProfile function from the profile service.
  const { getProfile } = useProfileService()

  /**
   * Fetch the user profile data from the server.
   * @returns {Promise<Object>} A promise that resolves to the profile data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['profile'],
    queryFn: () => getProfile(),
    select: (data) => data.response,
    onError: () => {
      toast('Error al obtener el perfil', { type: 'error' })
    }
  })

  return { ...queryResults }
}
