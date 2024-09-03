<template>
  <footer class="footer">
    <div class="footer-body bg-secondary">
      <div class="container border-primary">
        <div class="row">
          <div class="col-md-12 text-center text-white">
            <p class="mb-0">© {{ date }} {{ appName }}, Todos los derechos reservados.</p>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script>
import { computed, ref } from 'vue'
import { useStore } from 'vuex'

export default {
  data() {
    return {
      date: new Date().getFullYear()
    }
  },
  setup() {
    const store = useStore()
    const appName = computed(() => store.getters['setting/app_name'])
    const show = ref(false)
    return {
      appName,
      show
    }
  }
}
</script>
