<template>
  <div id="loading">
    <div :class="`loader simple-loader ${!show ? 'd-none' : ''}`">
      <div class="loader-body">
        <img class="light-loader img-fluid w-25" src="@/assets/images/loader.webp" alt="loader" width="200" height="200" />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    show: {
      type: Boolean,
      default: false
    }
  }
}
</script>

<style lang="scss" scoped></style>
