<template>
  <section class="login-content">
    <div class="row m-0 align-items-center bg-white vh-100">
      <div class="col-md-6 d-md-block d-none bg-primary p-0 vh-100 overflow-hidden">
        <img class="img-fluid gradient-main animated-scaleX" src="@/assets/images/auth/02.png" alt="images" loading="lazy" />
      </div>
      <div class="col-md-6 p-0">
        <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
          <div class="card-body">
            <div class="d-flex justify-content-center align-items-center">
              <router-link class="navbar-brand d-flex align-items-center mb-3 text-primary" :to="{ name: 'default.dashboard' }">
                <brand-logo width="100" height="100"></brand-logo>
                <!-- <h4 class="logo-title ms-3 mb-0"><brand-name></brand-name></h4> -->
              </router-link>
            </div>
            <h2 class="mb-2">Restablecer la contraseña</h2>
            <p>Ingrese su dirección de correo electrónico y le enviaremos un correo electrónico con instrucciones para restablecer su contraseña.</p>
            <form>
              <div class="row">
                <div class="col-lg-12">
                  <div class="floating-label form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" type="email" aria-describedby="email" placeholder=" " />
                  </div>
                </div>
              </div>
              <button class="btn btn-primary" type="submit">Enviar</button>
            </form>
          </div>
        </div>
        <div class="sign-bg sign-bg-right">
          <svg width="280" height="230" viewBox="0 0 431 398" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g opacity="0.05">
              <rect x="-157.085" y="193.773" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 -157.085 193.773)" fill="#3B8AFF" />
              <rect x="7.46875" y="358.327" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 7.46875 358.327)" fill="#3B8AFF" />
              <rect x="61.9355" y="138.545" width="310.286" height="77.5714" rx="38.7857" transform="rotate(45 61.9355 138.545)" fill="#3B8AFF" />
              <rect x="62.3154" y="-190.173" width="543" height="77.5714" rx="38.7857" transform="rotate(45 62.3154 -190.173)" fill="#3B8AFF" />
            </g>
          </svg>
        </div>
      </div>
    </div>
  </section>
</template>
