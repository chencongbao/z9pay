import permision from "./permission.js"

export default {
	install(Vue) {
		Vue.prototype.$tip = function(message, callback) {
			uni.showModal({
				title: "Tip",
				content: message,
				showCancel: false,
				success: () => {
					if (typeof callback == 'function') {
						callback();
					}
				}
			})
		}

		Vue.prototype.$confirm = function(message, callback) {
			if (typeof message == 'function') {
				uni.showModal({
					title: "提示",
					content: "确定操作?",
					success: (res) => {
						if (res.confirm) {
							message();
						}
					}
				})
			} else {
				uni.showModal({
					title: "提示",
					content: message,
					success: (res) => {
						if (res.confirm) {
							if (typeof callback == 'function') {
								callback();
							}
						}
					}
				})
			}

		}

		Vue.prototype.$message = function(message) {
			uni.showToast({
				title: message,
				icon: "none",
			});
		}

		Vue.prototype.$trim = function(str) {
			return str.replace(/\s+/g, '').replace(/ss*$/, '').replace(
				/\u0000|\u0001|\u0002|\u0003|\u0004|\u0005|\u0006|\u0007|\u0008|\u0009|\u000a|\u000b|\u000c|\u000d|\u000e|\u000f|\u0010|\u0011|\u0012|\u0013|\u0014|\u0015|\u0016|\u0017|\u0018|\u0019|\u001a|\u001b|\u001c|\u001d|\u001e|\u001f/g,
				"");
		}

		Vue.prototype.$isempty = function(e) {
			return !e || JSON.stringify(e) === '{}' || e instanceof Array && !e.length;
		}

		Vue.prototype.$back = function(e) {
			uni.navigateBack();
		}

		Vue.prototype.$preview = function(images) {
			uni.previewImage({
				urls: [images]
			});
		}

		Vue.prototype.$copy = function(data) {
			uni.setClipboardData({
				data: String(data),
				success: function() {
					uni.$u.toast('复制成功');
				},
				fail: function(e) {
					uni.$u.toast('复制失败');
				}
			})
		}



		Vue.prototype.$formatDate = function(value) {
			let formatValue = value.replace(/-/g, '/');
			return new Date(formatValue);
		}


		Vue.prototype.$addCommas = function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		}

		Vue.prototype.$isTabBar = function(url) {
			let tabBar = ["/pages/index/index", "/pages/lottery/index/index", "/pages/user/index/index",
				"/pages/chart/index/index"
			];
			if (tabBar.indexOf(url) > -1) return true;
			return;
		}



		Vue.prototype.$strLeft0 = function(num) {
			return String(num).padStart(2, '0');
		}

		Vue.prototype.$money = function(value) { // 价格的限制规则，只能输入小数点后两位
			return ("" + value) // 第一步：转成字符串
				.replace(/[^\d^\.]+/g, "") // 第二步：把不是数字，不是小数点的过滤掉
				.replace(/^0+(\d)/, "$1") // 第三步：第一位0开头，0后面为数字，则过滤掉，取后面的数字
				.replace(/^\./, "0.") // 第四步：如果输入的第一位为小数点，则替换成 0. 实现自动补全
				.match(/^\d*(\.?\d{0,2})/g)[0] || 0; // 第五步：最终匹配得到结果 以数字开头，只有一个小数点，而且小数点后面只能有0到2位小数
		}

		Vue.prototype.$navigate = function(url) {
			uni.navigateTo({
				url: url
			})
		}

		Vue.prototype.$parseDomain = function(domain) {
			return String(domain || '')
				.replace(/^https?:\/\//i, '')
				.replace(/\/api\/?$/i, '')
				.replace(/\/+$/, '');
		}
	}
}
