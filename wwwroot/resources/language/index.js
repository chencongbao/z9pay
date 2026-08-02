import Vue from 'vue'
import VueI18n from 'vue-i18n'
Vue.use(VueI18n)
import lang from './vue-i18n-locales'

const config = window.__APP_CONFIG__ || { locale: 'zh_CN' }

export default new VueI18n({
  locale:config.locale,
  messages:lang
})
