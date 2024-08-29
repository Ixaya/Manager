//Code Created By: Kevin Martinez
//Code Created Date: 29/05/2024
// Path: src/common/services/profileService/index.js
import { useAxios } from '../api'

const { client } = useAxios()

/**
 * Service to interact with the user profile.
 */
export const useProfileService = () => {
  return {
    /** Fetches the user profile data from the server.
     * @returns {Promise<Object>} A promise that resolves to the profile data.
     * @throws {Error} Throws an error if the request fails.
     * */
    getProfile: () => {
      return client
        .get('admin/api/profile')
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /** Updates the user profile data on the server.
     * @param {Object} data The data to update the profile with.
     * @returns {Promise<Object>} A promise that resolves to the updated profile data.
     * @throws {Error} Throws an error if the request fails.
     * */
    updateProfile: (data) => {
      return client
        .post('admin/api/profile/edit', data)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    }
  }
}
