<template>
  <div class="card mb-3">
    <div class="row g-0">
      <div class="col-md-4 col-xl-2">
        <img class="img-fluid rounded-start" :src="`${testImage}`" :alt="id" />
      </div>
      <div class="col-md-8 col-xl-10">
        <div class="card-body">
          <h6 class="mb-2">{{ testTitle }}</h6>
          <div class="d-flex align-items-center">
            <RatingFillWidget :rating="4" fill="true" />
          </div>
          <p class="pt-2 mb-0">{{ testText }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import RatingFillWidget from './RatingFillWidget.vue'

export default {
  props: {
    testImage: { type: String, default: '' },
    testTitle: { type: String, default: '' },
    id: { type: String, default: '' },
    testText: { type: String, default: '' }
  },
  components: { RatingFillWidget }
}
</script>
