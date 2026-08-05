import App from "./App";
let configs = process.env.NODE_ENV === "development" ? require("@/env/dev.js") : require("@/env/prod.js");

//import VConsole from 'vconsole';

// #ifndef VUE3
import Vue from "vue";
import uView from "uview-ui";
import store from "./store";
import { router, RouterMount } from "./router/index.js"; //路径换成自己的

Vue.use(router);

import bob_headerVue from "./components/bob_header/bob_header.vue";
import IconSvg from "./components/svg_icon/svg_icon.vue";
import bob_inputVue from "./components/bob_input/bob_input.vue";
import bob_selectVue from "./components/bob_select/bob_select.vue";
import bob_searchVue from "./components/bob_search/bob_search.vue";
import bob_detailVue from "./components/bob_detail/bob_detail.vue";
import bob_tabVue from "./components/bob_tab/bob_tab.vue";

Vue.prototype.$appName = "Z9PAY";
Vue.prototype.$logo = "logo.png";

import "./uni.promisify.adaptor";
import Echo from "laravel-echo";
import Pusher from "pusher-js";
Vue.config.productionTip = false;

import ajax from "@/static/js/ajax.js";
Vue.prototype.$ajax = ajax;
import utils from "@/static/js/utils.js";
Vue.use(utils);

let script = document.createElement("script");
script.type = "text/javascript";
script.src = "/static/js/svg.js";
document.head.appendChild(script);

let script2 = document.createElement("script");
script2.type = "text/javascript";
script2.src = "/static/js/audioPlayPlugin.min.js";
document.head.appendChild(script2);

window.Pusher = Pusher;
window.reconnectEcho = function(apiUrl, restoreChannels = true) {
  const normalizedUrl = String(apiUrl || configs.baseUrl || "");
  const socketHost = Vue.prototype.$parseDomain(normalizedUrl);
  if (!socketHost) return;
  if (window.Echo && window.EchoApiUrl === normalizedUrl) return;
  if (window.Echo) window.Echo.disconnect();
  const isSecureApi = normalizedUrl.startsWith("https://");
  window.Echo = new Echo({
    broadcaster: "pusher", key: "963d39722f60853bf48f", wsHost: socketHost,
    wsPort: 6001, wssPort: 443, cluster: "ap1", forceTLS: isSecureApi,
    disableStats: true, enabledTransports: ["ws", "wss"],
  });
  window.EchoApiUrl = normalizedUrl;
  const pusher = window.Echo.connector.pusher;
  if (pusher.connection.state === "initialized" || pusher.connection.state === "disconnected") pusher.connect();
  if (restoreChannels && store.state.login) {
    store.commit("watchTransferNotice", store.state.transfer_notice);
    store.commit("watchDepositNotice", store.state.deposit_notice);
  }
};
window.reconnectEcho(uni.getStorageSync("api_domain") || configs.baseUrl, false);

Vue.component("IconSvg", IconSvg);
Vue.component("BobHeader", bob_headerVue);
Vue.component("BobInput", bob_inputVue);
Vue.component("BobSelect", bob_selectVue);
Vue.component("BobSearch", bob_searchVue);
Vue.component("BobDetail", bob_detailVue);
Vue.component("BobTab", bob_tabVue);

Vue.use(uView);
uni.$u.config.unit = "rpx";

uni.$zp = {
  config: {
    "refresher-img-style": {
      animation: "mescrollRotate .6s linear infinite",
    },
    "refresher-default-img": "/static/img/logo.png",
    "refresher-pulling-img": "/static/img/logo.png",
    "refresher-refreshing-img": "/static/img/logo.png",
    "refresher-complete-img": "/static/img/logo.png",
    "empty-view-img": "/static/img/empty.png",
    "auto-show-back-to-top": true,
    "default-page-size": "10",
    "empty-view-text": "暂无相关记录",
  },
};

App.mpType = "app";
const app = new Vue({
  store,
  ...App,
});
// #ifdef H5
RouterMount(app, router, "#app");
// #endif

// #ifndef H5
app.$mount();
// #endif

// #endif

// #ifdef VUE3
import { createSSRApp } from "vue";
export function createApp() {
  const app = createSSRApp(App);
  return {
    app,
  };
}
// #endif
