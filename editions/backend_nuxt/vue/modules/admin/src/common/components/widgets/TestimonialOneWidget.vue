<template>
  <div class="position-relative">
    <div class="shape">
      <img class="img-fluid" src="../../assets/modules/landing-pages/images/home-2/shape.png" :alt="id" />
    </div>
    <div class="card-body position-relative">
      <h6 class="mb-3 mt-3">{{ testTitle }}</h6>
      <p>{{ testText }}</p>
      <div class="border-bottom"></div>
      <div class="d-flex align-items-center mt-3">
        <img class="img-fluid rounded-pill" :src="`${testUser}`" :alt="id" />
        <div class="ms-3">
          <p class="mb-2">{{ userTitle }}</p>
          <div class="d-flex align-items-center">
            <RatingFillWidget :rating="4" fill="true" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import RatingFillWidget from '../widgets/RatingFillWidget.vue'

export default {
  component: {
    RatingFillWidget
  },
  props: {
    id: { type: String, default: '' },
    testTitle: { type: String, default: '' },
    testText: { type: String, default: '' },
    testUser: { type: String, default: '' },
    userTitle: { type: String, default: '' }
  },
  components: { RatingFillWidget }
}
</script>
