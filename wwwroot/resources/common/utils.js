export default {
    install(Vue) {
        //判断空函数
        Vue.prototype.$isEmpty = function(e) {
            return !e || JSON.stringify(e) === '{}' || e instanceof Array && !e.length;
        }
        //字符串打码
        Vue.prototype.$maskedString = function (val, head = 6, tail = 4) {
            const str = String(val || '')
            if (str.length <= 24) return str
            if (str.length <= head + tail + 4) return str
            const start = str.slice(0, head)
            const end = str.slice(-tail)
            return `${start}******${end}`
        }
        //判断是否移动设备
        Vue.prototype.$isPhone = function () {
            return navigator.userAgent.match(
                /(phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone)/i
            );
        }
    }
}
