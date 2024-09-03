import { createApp } from 'vue'
import App from './App.vue'
import './registerServiceWorker'
import router from './router'
import store from './common/store'
import Vue3Toasity from 'vue3-toastify'
import { VueQueryPlugin } from '@tanstack/vue-query'
import PrimeVue from 'primevue/config'
import 'vue3-toastify/dist/index.css'
import 'bootstrap-icons/font/bootstrap-icons.css'

// Library Components
import VueSweetalert2 from 'vue-sweetalert2'
import VueApexCharts from 'vue3-apexcharts'
import BootstrapVue3 from 'bootstrap-vue-3'
import Vue3Autocounter from 'vue3-autocounter'
import 'aos/dist/aos.css'

// Custom Components & Directives
import 'primevue/resources/themes/aura-light-green/theme.css'
import globalComponent from './common/plugins/global-components'
import globalDirective from './common/plugins/global-directive'
import globalMixin from './common/plugins/global-mixin'

require('waypoints/lib/noframework.waypoints.min')

const app = createApp(App)
app.use(store).use(router)

// Library Components
app.use(VueSweetalert2)
app.use(VueApexCharts)
app.use(BootstrapVue3)
app.use(VueQueryPlugin)
app.use(PrimeVue)
app.use(Vue3Toasity, {
  autoClose: 3000
})
app.component('vue3-autocounter', Vue3Autocounter)

// Custom Components & Directives
app.use(globalComponent)
app.use(globalDirective)
app.mixin(globalMixin)

app.mount('#app')

export default app
