<template>
  <div>
    <!-- Sub Header Component Start Here-->
    <sub-header title="Perfil" description=""></sub-header>
    <!-- Sub Header Component End Here-->
  </div>
  <div class="container-fluid px-2 px-md-4">
    <b-row>
      <b-col lg="12">
        <b-card>
          <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex flex-wrap align-items-center">
              <div class="profile-img position-relative me-3 mb-3 mb-lg-0 profile-logo profile-logo1">
                <img class="theme-color-default-img img-fluid rounded-pill avatar-100" v-if="userData?.image.url" :src="userData.image.url" alt="User-Profile" loading="lazy" />
                <img class="theme-color-default-img img-fluid rounded-pill avatar-100" v-else src="@/assets/images/avatars/01.png" alt="profile-pic" loading="lazy" />
              </div>
              <div class="d-flex flex-wrap align-items-center mb-3 mb-sm-0">
                <h4 class="me-2 h4">{{ userData.full_name }}</h4>
                <span>- {{ level_user }}</span>
              </div>
            </div>
          </div>
        </b-card>
      </b-col>
      <b-col lg="12">
        <div class="profile-content">
          <div id="profile-profile">
            <profile-widget />
          </div>
        </div>
      </b-col>
    </b-row>
  </div>
</template>
<script>
import { ref, onMounted } from 'vue'
import SubHeader from '@/common/components/custom/header/SubHeader.vue'
import ProfileWidget from '@/common/components/widgets/users/ProfileWidget.vue'
export default {
  components: {
    ProfileWidget,
    SubHeader
  },
  setup() {
    const userData = ref(JSON.parse(localStorage.getItem('user')))
    const level_user = ref(null)

    const permissions = () => {
      let level = userData.value.user_groups
      for (let i = 0; i < level.length; i++) {
        if (level[i] === 'admin') {
          level_user.value = 'Administrador'
          return true
        }
      }
    }

    onMounted(() => {
      permissions()
    })

    return {
      userData,
      permissions,
      level_user
    }
  }
}
</script>
