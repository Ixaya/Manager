<template>
  <div>
    <!-- Sub Header Component Start Here-->
    <sub-header title="Dashboard" description=""></sub-header>
    <!-- Sub Header Component End Here-->
  </div>
  <div class="container-fluid px-2 px-md-4">
    <b-row v-show="!isLoading">
      <b-col class="col-lg-3 col-md-6">
        <b-card>
          <div class="d-flex justify-content-between align-items-center">
            <div class="bg-info text-white rounded p-3">
              <icon-component class="card-slie-arrow" type="solid" icon-name="user-group" :size="25" />
            </div>
            <div class="text-end">
              Usuarios
              <h2>
                <vue3-autocounter ref="counter" :startAmount="0" :endAmount="data?.users_count" />
              </h2>
            </div>
          </div>
          <hr class="hr-horizontal" />
          <div class="text-center">
            <router-link :to="{ name: 'default.user-list' }" class="mt-1 btn btn-outline-success d-block rounded">Mostrar usuarios</router-link>
          </div>
        </b-card>
      </b-col>
      <!-- <b-col class="col-lg-3 col-md-6">
        <b-card>
          <div class="d-flex justify-content-between align-items-center">
            <div class="bg-warning text-white rounded p-3">
              <icon-component class="card-slie-arrow" type="solid" icon-name="document" :size="25" />
            </div>
            <div class="text-end">
              Web Pages
              <h2>
                <Vue3autocounter ref="counter" :startAmount="0" :endAmount="data?.webpage_count" />
              </h2>
            </div>
          </div>
          <hr class="hr-horizontal" />
          <div class="text-center">
            <a href="#" class="mt-1 btn btn-outline-primary d-block rounded">Mostrar Web Pages</a>
          </div>
        </b-card>
      </b-col> -->
    </b-row>
    <b-row v-show="isLoading">
      <b-col class="col-lg-3 col-md-6">
        <Skeleton width="20rem" height="7rem"></Skeleton>
      </b-col>
      <!-- <b-col class="col-lg-3 col-md-6">
        <Skeleton width="20rem" height="7rem"></Skeleton>
      </b-col> -->
    </b-row>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import Skeleton from 'primevue/skeleton'

// Import custom composables
import { useDashboard } from '@/common/composables/dashboard/useDashboard'

// Import custom components
import SubHeader from '@/common/components/custom/header/SubHeader.vue'
import IconComponent from '@/common/components/icons/IconComponent.vue'

// ===========================
// Reactive State
// ===========================
const { data, isLoading } = useDashboard()
</script>
