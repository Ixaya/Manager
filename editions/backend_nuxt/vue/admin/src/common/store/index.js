import { createStore, createLogger } from 'vuex'
import setting from './setting'

const debug = process.env.NODE_ENV !== 'production'

export default createStore({
  state: {
    isAuthenticated: false,
    shareOffcanvas: false
  },
  getters: {
    shareOffcanvas: (state) => state.shareOffcanvas
  },
  mutations: {
    login(state, payload) {
      state.isAuthenticated = true
      state.user = payload.user
      state.token = payload.token
      localStorage.setItem('token', payload.token) // store the token in local storage
      localStorage.setItem('user', JSON.stringify(payload.user)) // store the user in local storage
    },
    logout(state) {
      state.isAuthenticated = false
      state.user = null
      state.token = null
      localStorage.removeItem('token') // remove the token from local storage
      localStorage.removeItem('user') // remove the user from local storage
    },
    openBottomCanvasCommit(state, payload) {
      state[payload.name] = payload.value
    }
  },
  actions: {
    logout({ commit }) {
      commit('logout')
    },
    openBottomCanvasAction({ commit }, payload) {
      commit('openBottomCanvasCommit', payload)
    }
  },
  modules: {
    setting: setting
  },
  strict: debug,
  plugins: debug ? [createLogger()] : []
})
