import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from './views/LandingPage.vue'
import HomeView from './views/HomeView.vue'
import LoginView from './views/LoginView.vue'
import LoginConfirmView from './views/LoginConfirmView.vue'
import RegisterView from './views/RegisterView.vue'
import SessionsView from './views/SessionsView.vue'
import AboutView from './views/AboutView.vue'
import ImpressumView from './views/legal/ImpressumView.vue'
import DatenschutzView from './views/legal/DatenschutzView.vue'
import NotFoundView from './views/NotFoundView.vue'
import { isAuthenticated } from './api/client'

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
    // Everything below requires authentication (see the global guard).
    { path: '/app', name: 'app-home', component: HomeView },
    { path: '/app/sessions', name: 'sessions', component: SessionsView },
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
