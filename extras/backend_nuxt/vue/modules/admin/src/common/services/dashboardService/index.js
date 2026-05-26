//Code Created By: Kevin Martinez
//Code Created Date: 04/06/2024
// Path: src/common/services/dashboardService/index.js

// Import the required libraries and hooks.
import { useAxios } from '../api'

const { client } = useAxios()

/**
 * Service to interact with the user dashboard.
 */
export const useDashboardService = () => {
  return {
    /**
     * Fetches the user dashboard data from the server.
     *
     * @returns {Promise<Object>} A promise that resolves to the dashboard data.
     * @throws {Error} Throws an error if the request fails.
     */
    getDashboard: () => {
      return client
        .get('admin/api/dashboard')
        .then((response) => response.data)
        .catch((error) => {
          console.error(error)
          throw error
        })
    }
  }
}
