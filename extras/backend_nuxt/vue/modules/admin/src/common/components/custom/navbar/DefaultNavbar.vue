<template>
  <nav :class="`nav navbar navbar-expand-xl navbar-light iq-navbar ${headerNavbar}`">
    <!-- <nav :class="`nav navbar navbar-expand-xl navbar-light iq-navbar ${headerNavbar} ${navbarHide.join('')}`"> -->
    <div class="container-fluid navbar-inner">
      <slot></slot>
      <div class="input-group search-input" v-if="isSearch">
        <span class="input-group-text" id="search-input">
          <icon-component type="outlined" :size="18" icon-name="search"></icon-component>
        </span>
        <input type="search" class="form-control" placeholder="Search..." />
      </div>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon">
          <span class="mt-2 navbar-toggler-bar bar1"></span>
          <span class="navbar-toggler-bar bar2"></span>
          <span class="navbar-toggler-bar bar3"></span>
        </span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="mb-2 navbar-nav ms-auto align-items-center navbar-list mb-lg-0">
          <li class="nav-item dropdown">
            <a class="nav-link" id="notification-drop" href="#" data-bs-toggle="dropdown">
              <icon-component type="dual-tone" icon-name="bell"></icon-component>
              <!-- Contador de notificaciones con estilo de burbuja -->
              <span class="notification-badge" v-if="notifications.length > 0">{{ unreadCount }}</span>
            </a>
            <div class="p-0 sub-drop dropdown-menu dropdown-menu-end" aria-labelledby="notification-drop">
              <b-card class="m-0 shadow-none" no-body>
                <div class="py-3 card-header d-flex justify-content-between bg-primary">
                  <b-card-title>
                    <h5 class="mb-0 text-white">Todas las notificaciones</h5>
                  </b-card-title>
                </div>
                <b-card-body class="p-0 card-scrollable">
                  <a href="#" class="iq-sub-card" v-for="notification in notifications" :key="notification.id" @click="handleNotification(notification.event, notification.id)" :class="{ 'notification-unread': notification.read == 1 }">
                    <div class="d-flex align-items-center">
                      <div class="w-100">
                        <p class="mb-1 text-muted small" v-html="notification.message"></p>
                        <div class="d-flex justify-content-between align-items-center">
                          <p class="mb-0"></p>
                          <small class="float-end font-size-10">{{ formatDate(notification.date) }}</small>
                        </div>
                      </div>
                    </div>
                  </a>

                  <div v-if="notifications.length === 0" class="text-center p-4">
                    <h6 class="text-muted">No hay notificaciones</h6>
                  </div>
                </b-card-body>
              </b-card>
            </div>
          </li>
          <li class="nav-item dropdown">
            <!-- <a class="nav-link" id="mail-drop" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <icon-component type="dual-tone" icon-name="message"></icon-component>
              <span class="bg-primary count-mail"></span>
            </a> -->
            <div class="p-0 sub-drop dropdown-menu dropdown-menu-end" aria-labelledby="mail-drop">
              <b-card class="m-0 shadow-none" no-body>
                <div class="py-3 card-header d-flex justify-content-between bg-primary">
                  <b-card-title>
                    <h5 class="mb-0 text-white">Todos los mensajes</h5>
                  </b-card-title>
                </div>
                <b-card-body class="p-0">
                  <a class="iq-sub-card" href="#">
                    <div class="d-flex align-items-center">
                      <div class="">
                        <img class="avatar-40 rounded-pill bg-soft-primary p-1" src="@/assets/images/shapes/01.png" alt="" />
                      </div>
                      <div class="ms-3">
                        <h6 class="mb-0">Bni Emma Watson</h6>
                        <small class="float-start font-size-12">13 Jun</small>
                      </div>
                    </div>
                  </a>
                  <a class="iq-sub-card" href="#">
                    <div class="d-flex align-items-center">
                      <div class="">
                        <img class="avatar-40 rounded-pill bg-soft-primary p-1" src="@/assets/images/shapes/02.png" alt="" />
                      </div>
                      <div class="ms-3">
                        <h6 class="mb-0">Lorem Ipsum Watson</h6>
                        <small class="float-start font-size-12">20 Apr</small>
                      </div>
                    </div>
                  </a>
                  <a class="iq-sub-card" href="#">
                    <div class="d-flex align-items-center">
                      <div class="">
                        <img class="avatar-40 rounded-pill bg-soft-primary p-1" src="@/assets/images/shapes/03.png" alt="" />
                      </div>
                      <div class="ms-3">
                        <h6 class="mb-0">Why do we use it?</h6>
                        <small class="float-start font-size-12">30 Jun</small>
                      </div>
                    </div>
                  </a>
                  <a class="iq-sub-card" href="#">
                    <div class="d-flex align-items-center">
                      <div class="">
                        <img class="avatar-40 rounded-pill bg-soft-primary p-1" src="@/assets/images/shapes/04.png" alt="" />
                      </div>
                      <div class="ms-3">
                        <h6 class="mb-0">Variations Passages</h6>
                        <small class="float-start font-size-12">12 Sep</small>
                      </div>
                    </div>
                  </a>
                  <a class="iq-sub-card" href="#">
                    <div class="d-flex align-items-center">
                      <div class="">
                        <img class="avatar-40 rounded-pill bg-soft-primary p-1" src="@/assets/images/shapes/05.png" alt="" />
                      </div>
                      <div class="ms-3">
                        <h6 class="mb-0">Lorem Ipsum generators</h6>
                        <small class="float-start font-size-12">5 Dec</small>
                      </div>
                    </div>
                  </a>
                </b-card-body>
              </b-card>
            </div>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link py-0 d-flex align-items-center" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img class="theme-color-default-img img-fluid avatar avatar-50 avatar-rounded" v-if="userData?.image.url" :src="userData.image.url" alt="User-Profile" />
              <img class="theme-color-default-img img-fluid avatar avatar-50 avatar-rounded" v-else src="@/assets/images/avatars/01.png" alt="User-Profile" />
              <div class="caption ms-3 d-none d-md-block">
                <h6 class="mb-0 caption-title">{{ userData.full_name }}</h6>
                <p class="mb-0 caption-sub-title">{{ level_user }}</p>
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
              <li>
                <router-link class="dropdown-item" :to="{ name: 'default.user-profile' }">Perfil</router-link>
              </li>
              <!-- <li><router-link class="dropdown-item" :to="{ name: 'default.user-privacy-setting' }">Privacy Setting</router-link></li> -->
              <li><hr class="dropdown-divider" /></li>
              <li>
                <button class="dropdown-item" @click="logout">Cerrar Sesión</button>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</template>
<script>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
export default {
  components: {},
  props: {
    isGoPro: {
      type: Boolean,
      default: false
    },
    isSearch: {
      type: Boolean,
      default: false
    }
  },
  setup(props, { emit }) {
    const router = useRouter()
    const store = useStore()
    const headerNavbar = computed(() => store.getters['setting/header_navbar'])
    const isHidden = ref(false)
    const userData = computed(() => JSON.parse(localStorage.getItem('user')))
    const level_user = ref(null)
    const notifications = ref([])

    const logout = () => {
      store.dispatch('logout')
      router.push({ name: 'auth.login' })
    }

    const permissions = () => {
      let level = userData.value.user_groups
      for (let i = 0; i < level.length; i++) {
        if (level[i] === 'admin') {
          level_user.value = 'Administrador'
          return true
        }
      }
    }

    const onscroll = () => {
      const yOffset = document.documentElement.scrollTop
      const navbar = document.querySelector('.navs-sticky')
      if (navbar !== null) {
        if (yOffset >= 100) {
          navbar.classList.add('menu-sticky')
        } else {
          navbar.classList.remove('menu-sticky')
        }
      }
    }

    const carts = computed(() => store.getters.carts)

    onMounted(() => {
      window.addEventListener('scroll', onscroll())
      permissions()
    })

    onUnmounted(() => {
      window.removeEventListener('scroll', onscroll())
    })
    return {
      headerNavbar,
      isHidden,
      carts,
      emit,
      logout,
      userData,
      permissions,
      level_user,
      notifications
    }
  }
}
</script>
