//Code Created By: Kevin Martinez
//Code Created Date: 04/06/2024
// Path: src/common/services/usersService/index.js

// Import the required libraries and hooks.
import { useAxios } from '../api'

const { client } = useAxios()

/**
 * Service to interact with the users.
 */
export const useUsersService = () => {
  return {
    /**
     * Fetches the users data from the server.
     *
     * @returns {Promise<Object>} A promise that resolves to the users data.
     * @throws {Error} Throws an error if the request fails.
     */
    getUsers: (params) => {
      return client
        .get('admin/api/sysusers', {
          params
        })
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Fetches the user roles from the server.
     * @returns {Promise<Object>} A promise that resolves to the user roles data.
     * @throws {Error} Throws an error if the request fails.
     * @returns {Promise<Object>} A promise that resolves to the user roles data.
     */
    getRoles: () => {
      return client
        .get('admin/api/sysusers/roles')
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Creates a new user on the server.
     * @param {Object} data The data to create the user with.
     * @returns {Promise<Object>} A promise that resolves to the created user data.
     * @throws {Error} Throws an error if the request fails.
     */
    createUser: (data) => {
      return client
        .post(`admin/api/sysusers/create`, data)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Fetches a user from the server.
     * @param {Number} id The id of the user to fetch.
     * @returns {Promise<Object>} A promise that resolves to the user data.
     * @throws {Error} Throws an error if the request fails.
     */
    showUser: (id) => {
      return client
        .get(`admin/api/sysusers/show/`, {
          params: {
            id: id
          }
        })
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Updates a user on the server.
     * @param {Object} data The data to update the user with.
     * @returns {Promise<Object>} A promise that resolves to the updated user data.
     * @throws {Error} Throws an error if the request fails.
     */
    updateUser: (data) => {
      return client
        .post(`admin/api/sysusers/update`, data)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Creates a new user on the server.
     *
     * @param {Object} data The data to create the user with.
     * @returns {Promise<Object>} A promise that resolves to the created user data.
     * @throws {Error} Throws an error if the request fails.
     */
    deleteUser: (id) => {
      return client
        .post(`/admin/api/sysusers/delete`, { id: id })
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    }
  }
}
