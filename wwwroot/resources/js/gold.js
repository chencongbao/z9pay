import Vue from "vue";
import App from "./Gold.vue";
import Vant from "vant";
import "vant/lib/index.css";
// import VConsole from 'vconsole';
//
// let vConsole = new VConsole();
// Vue.use(vConsole);

import ajax from "./../common/ajax";

Vue.use(Vant);
Vue.prototype.$ajax = ajax;

Vue.config.productionTip = false;

new Vue({
    render: (h) => h(App),
}).$mount("#app");
