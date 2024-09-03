<template>
  <div class="gradient">
    <b-container>
      <img class="img-fluid mb-4 w-50" src="@/assets/images/error/404.png" alt="400" loading="lazy" />
      <h2 class="mb-0 mt-4 text-white">¡Ups! Esta página no se encuentra.</h2>
      <p class="mt-2 text-white">La página solicitada no existe.</p>
      <router-link class="btn bg-white text-primary d-inline-flex align-items-center" :to="{ name: 'auth.login' }">Volver a Inicio</router-link>
    </b-container>
    <div class="box">
      <div class="c xl-circle">
        <div class="c lg-circle">
          <div class="c md-circle">
            <div class="c sm-circle">
              <div class="c xs-circle"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
