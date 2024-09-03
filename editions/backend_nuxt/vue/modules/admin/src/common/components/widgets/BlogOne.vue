<template>
  <div class="card">
    <div class="card-body p-3">
      <img class="img-fluid rounded" :src="`${blogImage}`" :alt="id" loading="lazy" />
      <p class="text-primary pt-3">{{ blogDate }}</p>
      <router-link class="my-3 h5" to="/blog-details">{{ blogTitle }}</router-link>
      <div class="d-flex align-items-center my-3">
        <span>{{ blogAuther }}</span>
        <span class="ms-3 text-primary">Admin</span>
      </div>
      <p class="pt-3">{{ blogDescription }}</p>
      <router-link class="btn btn-primary" to="/blog-details">Read More</router-link>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    id: { type: String, default: '' },
    blogImage: { type: String, default: '' },
    blogDate: { type: String, default: '' },
    blogAuther: { type: String, default: '' },
    blogTitle: { type: String, default: '' },
    blogDescription: { type: String, default: '' }
  }
}
</script>
