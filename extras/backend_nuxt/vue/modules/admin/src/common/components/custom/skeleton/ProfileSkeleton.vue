<template>
  <div>
    <div class="row mb-4">
      <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
        <b-card>
          <b-card-header class="d-flex justify-content-between">
            <div class="header-title">
              <Skeleton width="12rem" height="2rem"></Skeleton>
            </div>
          </b-card-header>
          <b-card-body>
            <div class="form-group">
              <div class="profile-img-edit position-relative">
                <Skeleton width="100%" height="8rem"></Skeleton>
              </div>
              <div class="img-extension mt-3">
                <div class="d-inline-block align-items-center">
                  <Skeleton width="100%"></Skeleton>
                  <Skeleton class="mt-2" width="100%"></Skeleton>
                </div>
              </div>
            </div>
            <div class="form-group">
              <p><Skeleton width="100%"></Skeleton></p>
              <p><Skeleton width="100%"></Skeleton></p>
              <p><Skeleton width="100%"></Skeleton></p>
              <p><Skeleton width="100%"></Skeleton></p>
            </div>
          </b-card-body>
        </b-card>
      </div>
      <div class="col-xl-9 col-lg-8 col-md-6 col-sm-12">
        <b-card>
          <b-card-header class="d-flex justify-content-between">
            <div class="header-title">
              <Skeleton width="14rem" height="2rem"></Skeleton>
            </div>
          </b-card-header>
          <b-card-body>
            <div class="new-user-info">
              <div class="row">
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
              </div>
              <hr />
              <Skeleton width="14rem" height="2rem"></Skeleton>
              <div class="row">
                <div class="col-md-12 col-sm-12 form-group">
                  <Skeleton class="mt-2" width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
                <div class="col-md-6 col-sm-12 form-group">
                  <Skeleton width="6rem"></Skeleton>
                  <Skeleton class="mt-2" width="100%" height="3rem"></Skeleton>
                </div>
              </div>
              <Skeleton width="14rem" height="3rem"></Skeleton>
            </div>
          </b-card-body>
        </b-card>
      </div>
    </div>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import Skeleton from 'primevue/skeleton'
</script>
