<template>
  <div class="main-content position-relative max-height-vh-100 h-100 overflow-x-hidden mx-3" id="page_layout">
    <slot></slot>
  </div>
</template>

<script>
import { computed } from 'vue'
import { useStore } from 'vuex'
export default {
  setup() {
    const store = useStore()
    const pageLayout = computed(() => store.getters['setting/page_layout'])
    return {
      pageLayout
    }
  }
}
</script>
