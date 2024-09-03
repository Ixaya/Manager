// Code Created By: Kevin Martinez
// Code Created Date: 04/06/2024
// Path: src/common/composables/dashboard/useDashboard.js

// Import the required libraries and hooks.
import { useQuery } from '@tanstack/vue-query'
import { toast } from 'vue3-toastify'
import { useDashboardService } from '@/common/services/dashboardService'

/**
 * Hook to fetch the user dashboard data.
 * @returns {Object} The user dashboard data.
 */
export const useDashboard = () => {
  // Get the getDashboard function from the dashboard service.
  const { getDashboard } = useDashboardService()

  /**
   * Fetch the user dashboard data from the server.
   * @returns {Promise<Object>} A promise that resolves to the dashboard data.
   * @throws {Error} Throws an error if the request fails.
   */
  const queryResults = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => getDashboard(),
    select: (data) => data.response,
    onError: () => {
      toast('Error al obtener el dashboard', { type: 'error' })
    }
  })

  return { ...queryResults }
}
