<template>
  <!--Logo start-->
  <div class="logo-main">
    <img :class="`${color ? 'text-primary' : ''}`" :width="width" :height="height" :src="logo" alt="logo" />
  </div>
  <!--Logo End-->
</template>

<script>
// ===========================
// Imports
// ===========================
// Import required modules
import { computed } from 'vue'
import { useStore } from 'vuex'

// ===========================
// Props
// ===========================
export default {
  props: {
    color: { type: Boolean, default: false },
    width: { type: Number, default: 30 },
    height: { type: Number, default: 30 }
  },
  setup() {
    const store = useStore()
    const appName = computed(() => store.getters['setting/app_name'])
    const logo = process.env.VUE_APP_LOGO

    return { appName, logo }
  }
}
</script>
