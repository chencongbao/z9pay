import Vue from "vue";
import App from "./App.vue";
import Vant from "vant";
import "vant/lib/index.css";
import VueClipboard from "vue-clipboard2";
import VueVideoPlayer from "vue-video-player";
import "video.js/dist/video-js.css";

Vue.prototype.$config = window.__APP_CONFIG__ || {}
import i18n from "./../language";
import ajax from "./../common/ajax";
import utils from './../common/utils'
import { CountDown } from 'vant';


import {registerOverrides} from '@/common/componentsRegister';

const ctx = {
    currency: Vue.prototype.$config?.currency || 'default',
    code: Vue.prototype.$config?.payment || 'default',
    system: Vue.prototype.$config?.app_name || 'default',
};
registerOverrides(Vue, {
    BottomBarActions: 'BottomBarActions.vue',
    CountdownTimer: 'CountdownTimer.vue',
    Empty: 'Empty.vue',
    ImportantAlert: 'ImportantAlert.vue',
    PayAmount: 'PayAmount.vue',
    PayCollectionInfo: 'PayCollectionInfo.vue',
    PayCopy: 'PayCopy.vue',
    PayFail: 'PayFail.vue',
    PayHeader: 'PayHeader.vue',
    PayQRCode: 'PayQRCode.vue',
    PaySuccess: 'PaySuccess.vue',
    PayWrapper: 'PayWrapper.vue',
    OpenAppButton: 'OpenAppButton.vue',
}, ctx);




Vue.use(utils);
Vue.use(Vant);
Vue.prototype.$ajax = ajax;
Vue.use(VueClipboard);
Vue.use(VueVideoPlayer);

Vue.config.productionTip = false;

new Vue({
    i18n,
    render: (h) => h(App),
}).$mount("#app");
