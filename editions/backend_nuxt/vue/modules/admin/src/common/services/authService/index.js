// Code Created By: Kevin Martinez
// Code Created Date: 29/05/2024
// Path: src/common/services/authService/index.js

// Import the required libraries and hooks.
import { useAxios } from '../api'

const { auth } = useAxios()

/**
 * Service to interact with the authentication.
 */
export const useAuthService = () => {
  return {
    /**
     * Logs the user in.
     * @param {Object} params The login parameters.
     * @returns {Promise<Object>} A promise that resolves to the login response.
     * @throws {Error} Throws an error if the request fails.
     */
    login: (params) => {
      return auth
        .post('auth/api/login', params)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Registers a new user.
     * @param {Object} params The registration parameters.
     * @returns {Promise<Object>} A promise that resolves to the registration response.
     * @throws {Error} Throws an error if the request fails.
     */
    register: (params) => {
      return auth
        .post('auth/api/login/register', params)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    }
  }
}
