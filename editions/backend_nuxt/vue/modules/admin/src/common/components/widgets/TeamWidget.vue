<template>
  <img class="img-fluid rounded-top" :src="`${teamImage}`" alt="team-details" loading="lazy" />
  <div class="card-body">
    <h6 class="my-2">{{ teamTitle }}</h6>
    <p class="mb-0">{{ teamText }}</p>
  </div>
</template>

<script>
export default {
  props: {
    teamTitle: { type: String, default: '' },
    teamImage: { type: String, default: '' },
    teamText: { type: String, default: '' }
  }
}
</script>
