<template>
	<view class="content">
		<view class="area">
			<view class="ul circles">
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
				<view class="li">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
				</view>
			</view>
		</view>
		<view class="login">
			<view class="main">
				<view class="title">
					<image v-bind:src="'../../static/img/'+$logo" mode="" class="img"></image>
					<view class="text"> {{ $appName }}</view>
				</view>
				<u--form ref="uForm" class="forms" :model="form" errorType="message" :rules="rules">
					<u-form-item prop="username" borderBottom>
						<u--input border="none" placeholder="用户名" v-model="form.username" maxlength="50" clearable></u--input>
					</u-form-item>
					<u-form-item prop="password" borderBottom>
						<u--input border="none" type="password" placeholder="密码" maxlength="50" v-model="form.password" clearable></u--input>
					</u-form-item>
					<u-form-item prop="vcode" borderBottom>
						<u--input placeholder="验证码" maxlength="10" type="number" border="none" v-model="form.vcode" clearable></u--input>
						<image :src="captcha" mode="" class="captcha" v-on:click="fetchCaptcha"></image>
					</u-form-item>
					<u-form-item borderBottom>
						<uni-data-select v-model="currentLineValue" :localdata="lineActions" placeholder="选择线路" @change="onSelectLine" :clear="false"></uni-data-select>
					</u-form-item>
					<u-button type="primary" text="确定" class="submit" color="#e8bd70" :loading="submitting" :disabled="submitting" v-on:click="loginSubmit"></u-button>
				</u--form>
			</view>
		</view>
		<bob_google v-model="showGoogle" :google="google" :username="form.username" :password="form.password"></bob_google>
	</view>
</template>

<script>
	import envConfig from '../../env';
	import bob_google from "@/components/bob_google/bob_google.vue"


	export default {
		data() {
			return {
				captcha: "",
				lineActions: [],
				currentLineValue: '',
				prevLineValue: '',
				submitting: false,
				showGoogle: false,
				form: {
					key: "",
					username: "",
					password: "",
					vcode: "",
				},
				google: {
					bind: 0,
					google_two_fa_secret: "",
					url: ""
				},
				rules: {
					username: {
						type: "string",
						required: true,
						message: "请填写用户名",
					},
					password: {
						type: "string",
						required: true,
						message: "请填写密码",
					},
					vcode: {
						type: "string",
						required: true,
						message: "请填写验证码",
					},
				},
			};
		},
		components: { bob_google },
		onLoad() {
			this.initLines();
			this.fetchCaptcha();
		},
		methods: {
			// 初始化所有线路
			initLines() {
				const list = [];
				if (envConfig.baseUrl) {
					list.push({
						text: '主线路',
						value: envConfig.baseUrl
					});
				}
				if (Array.isArray(envConfig.backupLines)) {
					envConfig.backupLines.forEach(str => {
						const [name, url] = String(str).split('|');
						if (url) {
							list.push({
								text: name.trim(),
								value: url.trim()
							});
						}
					});
				}
				this.lineActions = list;

				// 3. 读缓存
				const api_domain = uni.getStorageSync('api_domain');
				if (api_domain && list.some(item => item.value === api_domain)) {
					this.currentLineValue = api_domain;
					this.setBaseUrl(api_domain);
				} else if (list.length) {
					this.currentLineValue = list[0].value;
					this.setBaseUrl(list[0].value);
				}
				this.prevLineValue = this.currentLineValue;
			},
			async onSelectLine(val) {
				const item = this.lineActions.find(i => i.value === val);
				if (!item) return;

				try {
					const res = await this.$ajax.checkLine(item.value);

					// 2. OK 再真正切
					this.currentLineValue = item.value;
					this.prevLineValue = item.value;
					uni.setStorageSync('api_domain', item.value);
					this.setBaseUrl(item.value);

					// 3. 顺带把验证码填进来
					if (res && res.data) {
						this.captcha = res.data.img;
						this.form.key = res.data.key;
					}
				} catch (e) {
					// 测试失败，回到原来的线路
					this.currentLineValue = this.prevLineValue;
					uni.showToast({
						title: '该线路不可用，请切换其他线路',
						icon: 'none'
					});
				}
			},



			// 实际设置请求基地址
			setBaseUrl(url) {
				if (this.$ajax && typeof this.$ajax.setBaseUrl === 'function') {
					this.$ajax.setBaseUrl(url);
				}
			},
			fetchCaptcha() {
				return this.$ajax.get("v2/captcha/math", {}, { loading: false }).then((res) => {
					if (res) {
						this.captcha = res.data.img;
						this.form.key = res.data.key;
					}
				});
			},
			loginSubmit() {
				if (this.submitting) return;
				this.$refs.uForm.validate().then(async () => {
					this.submitting = true;
					try {
						const res = await this.$ajax.post("v2/checkLogin", this.form);
						if (res && res.data) {
							this.showGoogle = true;
							this.google = res.data;
						}
						await this.fetchCaptcha();
					} finally {
						this.submitting = false;
					}
				}).catch(() => {});
			}
		},
	};
</script>

<style>
	page {
		position: relative;
	}
</style>

<style lang="less" scoped>
	.content {
		background-color: #1c1c1c;
		height: 100vh;

		>.area {
			width: 100%;
			height: 100vh;
			position: fixed;

			>.circles {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				overflow: hidden;
				padding: 0;
				margin: 0;

				@-webkit-keyframes animate {
					0% {
						-webkit-transform: translateY(0) rotate(0deg);
						transform: translateY(0) rotate(0deg);
						opacity: 1;
						border-radius: 0;
					}

					to {
						-webkit-transform: translateY(-1000px) rotate(2turn);
						transform: translateY(-1000px) rotate(2turn);
						opacity: 0;
						border-radius: 50%;
					}
				}

				@keyframes animate {
					0% {
						-webkit-transform: translateY(0) rotate(0deg);
						transform: translateY(0) rotate(0deg);
						opacity: 1;
						border-radius: 0;
					}

					to {
						-webkit-transform: translateY(-1000px) rotate(2turn);
						transform: translateY(-1000px) rotate(2turn);
						opacity: 0;
						border-radius: 50%;
					}
				}

				>.li {
					position: absolute;
					width: 40rpx;
					height: 40rpx;
					filter: opacity(40%);
					animation: animate 25s linear infinite;
					bottom: -300rpx;

					>.img {
						width: 40rpx;
						height: 40rpx;
					}

					&:first-child {
						left: 25%;
						width: 160rpx;
						height: 160rpx;
						animation-delay: 0s;

						>.img {
							width: 160rpx;
							height: 160rpx;
						}
					}

					&:nth-child(2) {
						left: 10%;
						width: 40rpx;
						height: 40rpx;
						animation-delay: 2s;
						animation-duration: 12s;

						>.img {
							width: 40rpx;
							height: 40rpx;
						}
					}

					&:nth-child(3) {
						left: 70%;
						width: 40rpx;
						height: 40rpx;
						animation-delay: 4s;

						>.img {
							width: 40rpx;
							height: 40rpx;
						}
					}

					&:nth-child(4) {
						left: 40%;
						width: 120rpx;
						height: 120rpx;
						animation-delay: 0s;
						animation-duration: 18s;

						>.img {
							width: 120rpx;
							height: 120rpx;
						}
					}

					&:nth-child(5) {
						left: 65%;
						width: 40rpx;
						height: 40rpx;
						animation-delay: 0s;

						>.img {
							width: 40rpx;
							height: 40rpx;
						}
					}

					&:nth-child(6) {
						left: 75%;
						width: 220rpx;
						height: 220rpx;
						animation-delay: 3s;

						>.img {
							width: 220rpx;
							height: 220rpx;
						}
					}

					&:nth-child(7) {
						left: 35%;
						width: 300rpx;
						height: 300rpx;
						animation-delay: 7s;

						>.img {
							width: 300rpx;
							height: 300rpx;
						}
					}

					&:nth-child(8) {
						left: 50%;
						width: 50rpx;
						height: 50rpx;
						animation-delay: 15s;
						animation-duration: 45s;

						>.img {
							width: 50rpx;
							height: 50rpx;
						}
					}

					&:nth-child(9) {
						left: 20%;
						width: 40rpx;
						height: 40rpx;
						animation-delay: 2s;
						animation-duration: 35s;

						>.img {
							width: 40rpx;
							height: 40rpx;
						}
					}

					&:nth-child(10) {
						left: -15%;
						width: 300rpx;
						height: 300rpx;
						animation-delay: 5s;
						animation-duration: 11s;

						>.img {
							width: 300rpx;
							height: 300rpx;
						}
					}
				}
			}
		}

		>.login {
			box-sizing: border-box;
			overflow: hidden;
			position: fixed;
			background-color: rgba(255, 255, 255, 0.05);
			border-color: rgba(255, 255, 255, 0.05);
			border-radius: 8rpx;
			left: calc(var(--window-left) + 32rpx);
			right: calc(var(--window-right) + 32rpx);
			top: 50%;
			transform: translate(0, -50%);
			box-shadow: 0 10px 14px -6px rgba(0, 0, 0, 0.2), 0 22px 35px 3px rgba(0, 0, 0, 0.14), 0 8px 42px 7px rgba(0, 0, 0, 0.12) !important;

			>.main {
				box-sizing: border-box;
				min-width: 0;
				width: 100%;
				padding: 32rpx;

				>.title {
					display: flex;
					justify-content: center;
					align-items: center;
					padding-bottom: 32rpx;

					>.img {
						width: 100rpx;
						height: 100rpx;
					}

					>.text {
						padding-left: 32rpx;
						font-size: 48rpx;
						color: #e8bd70;
					}
				}

				>.forms {
					min-width: 0;
					width: 100%;

					::v-deep .u-form-item__body__right,
					::v-deep .u-form-item__body__right__content,
					::v-deep .u-form-item__body__right__content__slot {
						min-width: 0;
					}

					::v-deep .uni-input-input {
						color: #ffffff !important;
					}

					::v-deep .u-form-item__body__right__message {
						margin-left: 0rpx !important;
					}

					>.u-form-item {
						margin-bottom: 40rpx;
					}

					.captcha {
						flex: 0 1 250rpx;
						max-width: 42%;
						width: 250rpx;
						height: 80rpx;
						margin-left: 20rpx;
					}

					>.submit {
						margin-top: 64rpx;
					}
				}
			}
		}
	}

	@media screen and (max-width: 600px) {
		.content>.login {
			left: 16px;
			right: 16px;
			width: auto;

			>.main>.title {
				>.img {
					flex: 0 0 auto;
					width: 80rpx;
					height: 80rpx;
				}

				>.text {
					min-width: 0;
					padding-left: 20rpx;
					font-size: 32rpx;
					white-space: nowrap;
				}
			}
		}
	}
</style>
