import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from './views/LandingPage.vue'
import LoginView from './views/LoginView.vue'
import LoginConfirmView from './views/LoginConfirmView.vue'
import RegisterView from './views/RegisterView.vue'
import ProfileView from './views/ProfileView.vue'
import SettingsView from './views/SettingsView.vue'
import AboutView from './views/AboutView.vue'
import ImpressumView from './views/legal/ImpressumView.vue'
import DatenschutzView from './views/legal/DatenschutzView.vue'
import NotFoundView from './views/NotFoundView.vue'
import { api, isAuthenticated } from './api/client'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    { path: '/', name: 'landing', component: LandingPage, meta: { public: true } },
    { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { public: true } },
    {
      path: '/login/confirm',
      name: 'login-confirm',
      component: LoginConfirmView,
      meta: { public: true },
    },
    { path: '/about', name: 'about', component: AboutView, meta: { public: true } },
    {
      path: '/legal/impressum',
      name: 'legal-impressum',
      component: ImpressumView,
      meta: { public: true },
    },
    {
      path: '/legal/datenschutz',
      name: 'legal-datenschutz',
      component: DatenschutzView,
      meta: { public: true },
    },
    { path: '/id/:handle', name: 'profile', component: ProfileView, meta: { public: true } },
    // Everything below requires authentication (see the global guard).
    {
      path: '/app',
      name: 'app-home',
      // No profile page of its own – just sends the user straight to their
      // own /id/:handle card, which is where profile editing now lives.
      beforeEnter: async () => {
        try {
          const me = await api.me()
          return { name: 'profile', params: { handle: me.handle } }
        } catch {
          return { name: 'login' }
        }
      },
    },
    { path: '/settings', name: 'settings', component: SettingsView },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
  ],
})

router.beforeEach((to) => {
  if (to.meta.public) return

  if (!isAuthenticated()) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
})

export default router
