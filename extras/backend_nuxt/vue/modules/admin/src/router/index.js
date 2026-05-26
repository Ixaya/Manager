import { createRouter, createWebHistory } from 'vue-router'
// import store from '@/common/store/index'

// Auth Default Routes
const authChildRoutes = (prefix) => [
  {
    path: 'login',
    name: prefix + '.login',
    meta: { auth: false, name: 'Login' },
    component: () => import('@/views/auth/default/SignIn.vue')
  },
  {
    path: 'register',
    name: prefix + '.register',
    meta: { auth: false, name: 'Register' },
    component: () => import('@/views/auth/default/SignUp.vue')
  },
  {
    path: 'reset-password',
    name: prefix + '.reset-password',
    meta: { auth: false, name: 'Reset Password' },
    component: () => import('@/views/auth/default/ResetPassword.vue')
  },
  {
    path: 'varify-email',
    name: prefix + '.varify-email',
    meta: { auth: false, name: 'Varify Email' },
    component: () => import('@/views/auth/default/VarifyEmail.vue')
  }
]

const errorRoutes = (prefix) => [
  // Error Pages
  {
    path: '404',
    name: prefix + '.404',
    meta: { auth: false, name: 'Error 404', isBanner: true },
    component: () => import('@/views/errors/Error404Page.vue')
  },
  {
    path: '500',
    name: prefix + '.500',
    meta: { auth: false, name: 'Error 500', isBanner: true },
    component: () => import('@/views/errors/Error500Page.vue')
  },
  {
    path: 'maintenance',
    name: prefix + '.maintenance',
    meta: { auth: false, name: 'Maintenance', isBanner: true },
    component: () => import('@/views/errors/MaintenancePage.vue')
  }
]

// Default routes
const defaultChildRoutes = (prefix) => [
  {
    path: 'dashboard',
    name: prefix + '.dashboard',
    meta: { auth: true, name: 'Home', isBanner: true },
    component: () => import('@/views/dashboard/IndexPage.vue')
  },
  {
    path: 'user-list',
    name: prefix + '.user-list',
    meta: { auth: true, name: 'User List', isBanner: true },
    component: () => import('@/views/user/ListPage.vue')
  },
  {
    path: 'user-add',
    name: prefix + '.user-add',
    meta: { auth: true, name: 'User Add', isBanner: true },
    component: () => import('@/views/user/AddPage.vue')
  },
  {
    path: 'user-edit/:id',
    name: prefix + '.user-edit',
    meta: { auth: true, name: 'User Edit', isBanner: true },
    component: () => import('@/views/user/AddPage.vue')
  },
  {
    path: 'user-profile',
    name: prefix + '.user-profile',
    meta: { auth: true, name: 'User Profile', isBanner: true },
    component: () => import('@/views/user/ProfilePage.vue')
  },
  {
    path: 'example-list',
    name: prefix + '.example-list',
    meta: { auth: true, name: 'Example List', isBanner: true },
    component: () => import('@/views/example/ListPage.vue')
  },
  {
    path: 'example-add',
    name: prefix + '.example-add',
    meta: { auth: true, name: 'Example Add', isBanner: true },
    component: () => import('@/views/example/AddPage.vue')
  },
  {
    path: 'example-edit/:id',
    name: prefix + '.example-edit',
    meta: { auth: true, name: 'Example Edit', isBanner: true },
    component: () => import('@/views/example/AddPage.vue')
  }
]

// Landing Pages
const landingPageRoutes = (prefix) => [
  {
    path: '/terms-and-conditions',
    name: prefix + '.terms-and-conditions',
    meta: { auth: false, name: 'Terms and Conditions', isBanner: false },
    component: () => import('@/views/extra/TermsAndConditions.vue')
  },

  {
    path: '/',
    name: prefix + '.terms-and-conditions-home',
    meta: { auth: false, name: 'Terms and Conditions', isBanner: false },
    component: () => import('@/views/extra/TermsAndConditions.vue')
  }
]

const routes = [
  // Default Pages
  {
    path: '/admin',
    name: 'dashboard',
    redirect: { name: 'default.dashboard' },
    component: () => import('../layouts/DefaultLayout.vue'),
    children: defaultChildRoutes('default')
  },

  // Landing Pages
  {
    path: '/terms-and-conditions',
    name: 'landing-page',
    component: () => import('../layouts/guest/LandingLayout.vue'),
    children: landingPageRoutes('landing-page')
  },

  // Auth Skins
  {
    path: '/auth',
    name: 'auth',
    component: () => import('../layouts/guest/AuthLayout.vue'),
    children: authChildRoutes('auth')
  },
  // Errors Pages
  {
    path: '/errors',
    name: 'errors',
    component: () => import('../layouts/guest/BlankLayout.vue'),
    children: errorRoutes('errors')
  },
  // Default Skins
  {
    path: '/admin/:pathMatch(.*)*',
    name: 'notFound',
    beforeEnter: (to, from, next) => {
      const token = localStorage.getItem('token')
      if (token) {
        next({ name: 'default.dashboard' })
      } else {
        next({ name: 'auth.login' })
      }
    }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'notFound',
    redirect: { name: 'errors.404' }
  }
]

const router = createRouter({
  linkActiveClass: 'active',
  linkExactActiveClass: 'exact-active',
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  if (to.meta.auth && !token) {
    next({ name: 'auth.login' }) // redirect to login if the user is not authenticated
  } else if (to.name === 'auth.login' && token) {
    next({ name: 'default.dashboard' }) // redirect to dashboard if the user is authenticated and tries to access the login page
  } else {
    next() // proceed as normal if the user is authenticated or the route does not require auth
  }
})

export default router
