// Code Created By: Kevin Martinez
// Code Created Date: 31/05/2024
// Path: src/common/composables/profile/useProfile.js

// Import the required libraries and hooks.
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useProfileService } from '@/common/services/profileService'

/**
 * Hook to edit the user profile.
 * @returns {Object} The user profile data.
 */
export function useEditProfile() {
  const queryClient = useQueryClient()
  const { updateProfile } = useProfileService()

  /**
   * Edit the user profile data.
   * @param {Object} data The data to update the profile with.
   * @returns {Promise<Object>} A promise that resolves to the updated profile data.
   * @throws {Error} Throws an error if the request fails.
   */
  const { mutateAsync, isLoading, isError, data, isSuccess } = useMutation({
    mutationFn: (data) => updateProfile(data),
    onSuccess: (data) => {
      queryClient.invalidateQueries('profile')
      localStorage.setItem('user', JSON.stringify(data.response.profile))
    }
  })

  return {
    updateProfile: mutateAsync,
    isLoading,
    isError,
    isSuccess,
    data
  }
}
