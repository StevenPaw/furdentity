import { ref } from 'vue'

// Whether the current route has a full-viewport, fixed-position dark
// backdrop behind everything (see ProfileView.vue's .page-backdrop, shown
// whenever the profile being viewed has a background photo) - a route's own
// component doesn't reach global chrome that sits outside its tree (App.vue's
// header/footer), but that chrome still needs to know, since the backdrop is
// always dark regardless of the visitor's light/dark OS preference, while
// text elsewhere on the page is otherwise left to follow that preference
// automatically (see main.scss's `color-scheme: light dark`).
export const hasPageBackdrop = ref(false)
