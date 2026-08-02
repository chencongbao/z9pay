import {RouterMount,createRouter} from 'uni-simple-router';
import store from '@/store'

const router = createRouter({
	platform: process.env.VUE_APP_PLATFORM,  
	routes: [...ROUTES]
});
//全局路由前置守卫
router.beforeEach((to, from, next) => {
	if (to.meta.requireAuth !== undefined && to.meta.requireAuth === true) {
	    if (store.state.login) {
	      next();
	    } else {
	      next({ path: "/pages/login/login", query: { redirect: to.fullPath },NAVTYPE: 'push' });
	    }
	  } else {
	    next();
	  }
});
// 全局路由后置守卫
router.afterEach((to, from) => {
    console.log('跳转结束')
})

export {
	router,
	RouterMount
}