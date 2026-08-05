import ajax from 'uni-ajax'
import store from '@/store'
import Cookies from "js-cookie";
import envConfig from '../../env';

import {
	logout
} from "@/static/js/funtions"





const config = {
	baseURL: envConfig.baseUrl,
	withCredentials: true,
	loading: true,
	timeout: 120000
};

const cachedDomain = uni.getStorageSync('api_domain')
if (cachedDomain) {
	config.baseURL = cachedDomain
}

const instance = ajax.create(config)

let requestNum = 0;
let authPromptVisible = false;

function handleAuthFailure(message) {
	if (authPromptVisible) return;
	authPromptVisible = true;
	logout();
	store.commit('setLogin', false);
	store.commit('leaveTransferNotice');
	store.commit('leaveDepositNotice');
	uni.showModal({
		title: "提示",
		content: message,
		showCancel: false,
		complete: function() {
			uni.reLaunch({
				url: '/pages/login/login',
				complete: function() {
					authPromptVisible = false;
				}
			});
		}
	});
}

// 添加请求拦截器
instance.interceptors.request.use(
	config => {

		const latest = uni.getStorageSync('api_domain')
		if (latest) {
			config.baseURL = latest;
		}

		config.header['x-xsrf-token'] = Cookies.get('XSRF-TOKEN');
		if (uni.getStorageSync('m_token')) {
			config.header.Authorization = "Bearer " + uni.getStorageSync('m_token');
		}
		if (config.loading) {
			if (requestNum <= 0) {
				//打开加载动画
				uni.showLoading({
					title: "Loading",
					mask: true
				});
			}
			requestNum += 1;
		}

		return config
	},
	error => {
		if (error.config && error.config.loading) {
			requestNum = Math.max(0, requestNum - 1);
			if (requestNum <= 0) {
				uni.hideLoading();
			}
		}
		if (error.config && error.config.propagateError) {
			return Promise.reject(error);
		}
		uni.showModal({
			title: "提示",
			content: error.errMsg || error.message || "网络请求失败",
			showCancel: false
		});
	}
)

instance.setBaseUrl = function(url) {
	this.defaults.baseURL = url;
	uni.setStorageSync('api_domain', url);
	if (typeof window !== 'undefined' && typeof window.reconnectEcho === 'function') {
		window.reconnectEcho(url);
	}
}

// ✅ 新增：检测一条线路是否可用
instance.checkLine = function(url) {
	const testUrl = url.replace(/\/+$/, '/') + 'v2/captcha/math';
	return this.get({
		url: testUrl,
		method: 'GET',
		baseURL: '',
		loading: true,
		propagateError: true
	})
}

// 添加响应拦截器
instance.interceptors.response.use(
	response => {
		if (response.config.loading) {
			requestNum = Math.max(0, requestNum - 1);
			if (requestNum <= 0) {
				uni.hideLoading();
			}
		}
		if (response.statusCode == 200) {
			if (response.data.code == 200) {
				return response.data;
			}
			if (response.data.code == -1) {
				handleAuthFailure("请登录");
				return;
			}
			if (response.data.code == -2) {
				handleAuthFailure("您的账号已禁用，请联系客服");
				return;
			}
			uni.showModal({
				title: "提示",
				content: response.data.message,
				showCancel: false
			});
			return;
		}
		throw new Error("HTTP " + response.statusCode);
	},
	error => {
		if (error.config && error.config.loading) {
			requestNum = Math.max(0, requestNum - 1);
			if (requestNum <= 0) {
				uni.hideLoading();
			}
		}
		if (error.config && error.config.propagateError) {
			return Promise.reject(error);
		}
		uni.showModal({
			title: "提示",
			content: error.errMsg || error.message || "网络请求失败",
			showCancel: false
		});
	}
);

instance.uploadImage = function(filePath, formData, callback) {
	return new Promise(async (resolve, reject) => {
		const url = await this.getURL({
			url: 'v2/transfer-orders/uploadImage'
		});

		uni.showLoading({
			title: "上传中...",
			mask: true
		});

		const uploadTask = uni.uploadFile({
			url,
			filePath,
			name: 'file',
			header: {
				"Authorization": "Bearer " + uni.getStorageSync('m_token')
			},
			formData: typeof formData === 'object' ? formData : {},
			timeout: 60000,
			success: res => {
				let result;
				try {
					result = JSON.parse(res.data);
				} catch (error) {
					uni.showModal({ title: "提示", content: "上传响应格式错误", showCancel: false });
					return reject(error);
				}
				if (result.code == 200) {
					return resolve(result);
				} else {
					uni.showModal({
						title: "提示",
						content: result.message,
						showCancel: false
					});
					return reject('');
				}
			},
			fail: error => {
				uni.showModal({
					title: "提示",
					content: error.errMsg || "图片上传失败",
					showCancel: false
				});
				reject(error);
			},
			complete: res => {
				uni.hideLoading();
			}
		})

		// 如果第二个参数是 function 类型则作为 uploadTask 的回调函数使用，并不管第三个参数了
		if (typeof formData === 'function') {
			formData(uploadTask)
		} else if (typeof callback === 'function') {
			callback(uploadTask)
		}
	})
}

instance.uploadVideo = function(filePath, formData, callback) {
	return new Promise(async (resolve, reject) => {
		const url = await this.getURL({
			url: 'upload/video'
		})

		const uploadTask = uni.uploadFile({
			finalUrl,
			filePath,
			name: 'file',
			// 如果第二个参数是 object 类型则作为 formData 使用
			formData: typeof formData === 'object' ? formData : {},
			complete: res => (res.statusCode === 200 ? resolve(JSON.parse(res.data)) : reject(''))
		})

		// 如果第二个参数是 function 类型则作为 uploadTask 的回调函数使用，并不管第三个参数了
		if (typeof formData === 'function') {
			formData(uploadTask)
		} else if (typeof callback === 'function') {
			callback(uploadTask)
		}
	})
}



export default instance;
