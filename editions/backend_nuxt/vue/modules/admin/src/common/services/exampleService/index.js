//Code Created By: Kevin Martinez
//Code Created Date: 29/08/2024
// Path: src/common/services/exampleService/index.js

// Import the required libraries and hooks.
import { useAxios } from '../api'

const { client } = useAxios()

/**
 * Service to interact with the examples.
 */
export const useExampleService = () => {
  return {
    /**
     * Fetches the examples data from the server.
     *
     * @returns {Promise<Object>} A promise that resolves to the examples data.
     * @throws {Error} Throws an error if the request fails.
     */
    getExamples: (params) => {
      return client
        .get('admin/api/examples', {
          params
        })
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Creates a new example on the server.
     * @param {Object} data The data to create the example with.
     * @returns {Promise<Object>} A promise that resolves to the created example data.
     * @throws {Error} Throws an error if the request fails.
     */
    createExample: (data) => {
      return client
        .post(`admin/api/examples/create`, data)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Fetches a example from the server.
     * @param {Number} id The id of the example to fetch.
     * @returns {Promise<Object>} A promise that resolves to the example data.
     * @throws {Error} Throws an error if the request fails.
     */
    showExample: (id) => {
      return client
        .get(`admin/api/examples/show/`, {
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
     * Updates a example on the server.
     * @param {Object} data The data to update the example with.
     * @returns {Promise<Object>} A promise that resolves to the updated example data.
     * @throws {Error} Throws an error if the request fails.
     */
    updateExample: (data) => {
      return client
        .post(`admin/api/examples/update`, data)
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    },

    /**
     * Deletes a example on the server.
     * @param {Number} id The id of the example to delete.
     * @returns {Promise<Object>} A promise that resolves to the deleted example data.
     * @throws {Error} Throws an error if the request fails.
     */
    deleteExample: (id) => {
      return client
        .post(`/admin/api/examples/delete`, { id: id })
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    }
  }
}
