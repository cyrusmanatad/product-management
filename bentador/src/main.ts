import './assets/main.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

const app = createApp(App)

app.directive('outside-click', {
  mounted(el, binding) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event)
      }
    }
    document.addEventListener('click', el.clickOutsideEvent)
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent)
  },
})

const pinia = createPinia()
pinia.use(({ store }) => {
  if (['auth', 'tenant', 'cart', 'ui', 'toast'].includes(store.$id)) return
  const initial = JSON.parse(JSON.stringify(store.$state))
  window.addEventListener('tenant:reset', () => store.$patch(JSON.parse(JSON.stringify(initial))))
})
app.use(pinia)
app.use(router)

app.mount('#app')
