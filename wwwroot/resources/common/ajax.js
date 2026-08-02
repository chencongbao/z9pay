import axios from "axios";
import { Toast } from "vant";

const request = axios.create({
    baseURL: window.location.origin + "/",
    timeout: 50000,
    requestOptions: {
        withToken: true,
    },
});

// 数据请求拦截
request.interceptors.request.use(
    // 1. 返回config对象
    // 2. 可以设置携带 token 信息
    (config) => {
        if (!config.hideLoading) {
            Toast.loading({
                overlay: true,
                duration: 0,
                message: "Loading...",
                forbidClick: true,
                loadingType: "spinner",
            });
        }
        return config;
    },
    (err) => {
        return Promise.reject(err);
    }
);

// 返回响应数据拦截
request.interceptors.response.use(
    (response) => {
        if (!response.config.hideLoading) {
            Toast.clear();
        }
        return Promise.resolve(response.data);
    },
    // 错误执行
    (error) => {
        if (!error.config || !error.config.hideLoading) {
            Toast.clear();
        }
        return Promise.reject(error.message);
    }
);

// 暴露对象
export default request;
