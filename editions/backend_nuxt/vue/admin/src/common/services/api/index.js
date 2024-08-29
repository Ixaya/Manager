// Code Created By: Kevin Martinez
// Code Created Date: 29/05/2024
// Path: src/common/services/api/index.js

// Import the required libraries.
import axios from 'axios'
import { merge } from 'lodash'

/**
 * Hook to create axios instances.
 * @param {Object} options The options to configure the axios instance.
 * @returns {Object} The axios instances.
 */
export const useAxios = (options) => {
  /**
   * Default options for the axios instances.
   */
  const defaultOptions = {
    baseURL: process.env.VUE_APP_API_URL,
    headers: {
      'Content-Type': 'application/json'
    }
  }

  /**
   * Creates an Axios client instance with custom configuration options and request interceptors.
   *
   * @param {Object} defaultOptions - The default options for the Axios client.
   * @param {Object} options - Additional options to merge with the default options.
   * @returns {AxiosInstance} The configured Axios client instance.
   */
  const client = axios.create(merge(defaultOptions, options))
  client.interceptors.request.use((config) => {
    config.headers['X-API-KEY'] = localStorage.getItem('token')
    if (config.data instanceof FormData) config.headers['Content-Type'] = 'multipart/form-data'
    else config.headers['Content-Type'] = 'application/json'
    config.headers['Accept'] = 'application/json'

    return config
  })

  /**
   * Creates an Axios auth instance with custom configuration options and request interceptors.
   *
   * @param {Object} defaultOptions - The default options for the Axios auth instance.
   * @param {Object} options - Additional options to merge with the default options.
   * @returns {AxiosInstance} The configured Axios auth instance.
   */
  const auth = axios.create(merge(defaultOptions, options))
  auth.interceptors.request.use((config) => {
    config.headers['Content-Type'] = 'application/json'
    config.headers['Accept'] = 'application/json'
    return config
  })

  /**
   * Creates an Axios blob instance with custom configuration options and request interceptors.
   * This instance is used to download files from the server.
   * @param {Object} defaultOptions - The default options for the Axios blob instance.
   * @param {Object} options - Additional options to merge with the default options.
   * @returns {AxiosInstance} The configured Axios blob instance.
   */
  const blob = axios.create(merge(defaultOptions, options))
  blob.interceptors.request.use((config) => {
    config.headers['X-API-KEY'] = localStorage.getItem('token')
    config.headers['Content-Transfer-Encoding'] = 'binary'
    config.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    config.headers['Accept'] = 'application/json'

    return config
  })

  return { client, auth, blob }
}
