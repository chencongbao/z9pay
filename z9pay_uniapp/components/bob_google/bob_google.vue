<template>
	<u-popup :show="show" mode="center" :closeOnClickOverlay="false" :safeAreaInsetBottom="true" :overlayStyle="overlayStyle" @close="close" zIndex="100">
		<view class="gc-wrap">
			<!-- 顶部金色高光 -->
			<view class="gc-top-glow"></view>

			<!-- 标题区 -->
			<view class="gc-header">
				<view class="gc-header-left">
					<view class="gc-title-box">
						<text class="gc-title">谷歌验证码</text>
					</view>
				</view>
			</view>


			<!-- 绑定区：二维码 + 密钥 -->
			<view v-if="bind == 1" class="gc-bind">
				<view class="gc-qr-card" v-if="qrcode_url">
					<image v-if="qrcode_url" class="gc-qr" :src="qrcode_url" mode="aspectFit" />
				</view>

				<view v-if="google_two_fa_secret" class="gc-secret-card">
					<view class="gc-secret-row">
						<text class="gc-secret-label">密钥</text>
						<text class="gc-secret">{{ google_two_fa_secret }}</text>
					</view>
					<view class="gc-secret-actions">
						<u-button type="primary" @click="copy(google_two_fa_secret)">
							复制密钥
						</u-button>
					</view>
				</view>
			</view>

			<!-- 输入框 -->
			<view class="gc-input">
				<u-input v-model="form.google_2fa_code" type="number" :maxlength="6" placeholder="请输入 6 位谷歌验证码" clearable :focus="true" confirmType="done" @input="onInput" @confirm="handleSubmit" />
			</view>

			<!-- 按钮 -->
			<view class="gc-actions">
				<u-button type="info" plain @click="close">
					取消
				</u-button>

				<u-button type="primary" :disabled="!canSubmit" @click="handleSubmit">
					{{ bind == 1 ? '提交绑定' : '提交登录' }}
				</u-button>
			</view>
		</view>
	</u-popup>
</template>

<script>
	import {
		getLogin,
		getName,
		getUsername,
		getUserid,
		getStatus,
		getAgent,
		getDepositNotice,
		getTransferNotice,
		defaultVoice,
		getSelfAddBank,
		getAutoRefresh,
		getActionDelete,
		getActionLimitCard
	} from "@/static/js/funtions";
	export default {
		name: 'bob_google',
		props: {
			value: { type: Boolean, default: false },
			google: { type: Object, default: null },
			username: { type: String, default: "" },
			password: { type: String, default: "" }
		},
		data() {
			return {
				form: {
					username: '',
					password: '',
					google_2fa_code: ''
				},
				submitting: false
			}
		},
		watch: {
			username: {
				handler(newVal, oldVal) {
					this.form.username = newVal
				},
				immediate: true,
				deep: true
			},
			password: {
				handler(newVal, oldVal) {
					this.form.password = newVal
				},
				immediate: true,
				deep: true
			}
		},
		computed: {
			show() {
				return this.value
			},
			overlayStyle() {
				return {
					backgroundColor: 'rgba(0,0,0,0.75)',
					backdropFilter: 'blur(6px)',
					WebkitBackdropFilter: 'blur(6px)',
					zIndex: 99
				}
			},
			bind() {
				if (this.google) return this.google.bind || 0
				return 0
			},
			qrcode_url() {
				if (this.google) return this.google.url || ''
				return ''
			},
			google_two_fa_secret() {
				if (this.google) return this.google.google_two_fa_secret || ''
				return ''
			},
			canSubmit() {
				const reg = new RegExp(`^\\d{6}$`)
				return !this.submitting && reg.test(String(this.form.google_2fa_code || '').trim())
			}
		},
		methods: {
			close() {
				this.form.google_2fa_code = ''
				this.$emit('input', false)
				this.$emit('close')
			},
			onInput(val) {
				// 只保留数字，限制长度
				const cleaned = String(val || '').replace(/\D/g, '').slice(0, 6)
				if (cleaned !== this.form.google_2fa_code) this.form.google_2fa_code = cleaned
				if (this.error) this.error = ''
			},
			copy(text) {
				uni.setClipboardData({
					data: String(text || ''),
					success: () => uni.showToast({ title: '已复制', icon: 'none' })
				})
			},
			handleSubmit() {
				if (!this.canSubmit) return
				this.submitting = true
				this.$ajax.post("v2/checkGoogleVcode", this.form).then((res) => {
					if (res) {
						uni.setStorageSync("m_token", res.data.token);
						uni.setStorageSync("m_mtoken", res.data.mtoken);
						uni.setStorageSync("name", res.data.name);
						uni.setStorageSync("username", res.data.username);
						uni.setStorageSync("userid", res.data.userid);
						uni.setStorageSync("status", res.data.status);
						uni.setStorageSync("agent", res.data.agent);
						uni.setStorageSync("deposit_notice", res.data.deposit_notice);
						uni.setStorageSync("transfer_notice", res.data.transfer_notice);
						uni.setStorageSync("default_voice", res.data.default_voice);
						uni.setStorageSync("self_add_bank", res.data.self_add_bank);
						uni.setStorageSync("action_delete", res.data.action_delete);
						uni.setStorageSync("auto_refresh", res.data.auto_refresh);
						uni.setStorageSync("action_limit_card", res.data.action_limit_card);

						this.$store.commit("setLogin", true);
						this.$store.commit("setName", getName());
						this.$store.commit("setUsername", getUsername());
						this.$store.commit("setUserid", getUserid());
						this.$store.commit("setStatus", getStatus());
						this.$store.commit("setAgent", getAgent());
						this.$store.commit("setSelfAddBank", getSelfAddBank());
						this.$store.commit("setDepositNotice", getDepositNotice());
						this.$store.commit("setTransferNotice", getTransferNotice());
						this.$store.commit("setAutoRefresh", getAutoRefresh());
						this.$store.commit("setDefaultVoice", defaultVoice());
						this.$store.commit("watchTransferNotice", getTransferNotice());
						this.$store.commit("watchDepositNotice", getDepositNotice());
						this.$store.commit("setActionDelete", getActionDelete());
						this.$store.commit("setActionLimitCard", getActionLimitCard());

						uni.switchTab({
							url: "/pages/index/index",
						});
					}
				}).finally(() => {
					this.submitting = false
				});
			}
		}
	}
</script>

<style scoped>
	/* ================== White Popup Theme ================== */

	.gc-wrap {
		width: 600rpx;
		position: relative;
		overflow: hidden;
		border-radius: 20rpx;
		padding: 32rpx 28rpx 28rpx;

		background: #FFFFFF;
		border: 1rpx solid #E6E6E6;
		box-shadow:
			0 20rpx 60rpx rgba(0, 0, 0, 0.18);
	}

	/* 顶部高光去掉，白底不需要 */
	.gc-top-glow {
		display: none;
	}

	/* ===== Header ===== */
	.gc-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.gc-header-left {
		display: flex;
		align-items: center;
		gap: 16rpx;
	}

	.gc-logo {
		width: 56rpx;
		height: 56rpx;
		border-radius: 999rpx;
		background: #F2F2F2;
		border: 1rpx solid #E0E0E0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.gc-logo-text {
		color: #333333;
		font-weight: 800;
		font-size: 26rpx;
	}

	.gc-title-box {
		display: flex;
		flex-direction: column;
		gap: 4rpx;
	}

	.gc-title {
		color: #111111;
		font-size: 32rpx;
		font-weight: 700;
	}

	.gc-subtitle {
		color: #888888;
		font-size: 22rpx;
	}

	.gc-close {
		padding: 10rpx;
		border-radius: 999rpx;
		background: #F5F5F5;
		border: 1rpx solid #E0E0E0;
	}

	/* ===== 描述 ===== */
	.gc-desc {
		margin-top: 16rpx;
		color: #555555;
		font-size: 26rpx;
		line-height: 40rpx;
	}

	/* ===== 绑定区 ===== */
	.gc-bind {
		margin-top: 20rpx;
		display: flex;
		flex-direction: column;
		gap: 18rpx;
	}

	/* 二维码卡片：白底 + 灰框 */
	.gc-qr-card {
		width: 100%;
		height: 420rpx;
		border-radius: 16rpx;
		background: #FAFAFA;
		border: 1rpx solid #E5E5E5;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.gc-qr {
		width: 400rpx;
		height: 400rpx;
	}

	.gc-qr-skeleton {
		color: #999999;
		font-size: 26rpx;
	}

	/* 密钥卡片 */
	.gc-secret-card {
		border-radius: 14rpx;
		background: #FAFAFA;
		border: 1rpx solid #E5E5E5;
		padding: 32rpx;
	}

	.gc-secret-row {
		display: flex;
		flex-direction: column;
		gap: 12rpx;
	}

	.gc-secret-label {
		color: #777777;
		font-size: 24rpx;
	}

	.gc-secret {
		color: #222222;
		font-size: 24rpx;
		font-weight: 700;
		word-break: break-all;
	}

	.gc-secret-actions {
		margin-top: 12rpx;
		display: flex;
		justify-content: flex-end;
	}

	/* ===== 输入区 ===== */
	.gc-input {
		margin-top: 20rpx;
	}

	.gc-input-label {
		display: block;
		margin-bottom: 10rpx;
		color: #666666;
		font-size: 24rpx;
	}

	/* u-input 白色样式 */
	:deep(.u-input) {
		background: #FFFFFF !important;
		border: 1rpx solid #DCDCDC !important;
		border-radius: 12rpx !important;
		padding: 22rpx 18rpx !important;
		box-shadow: none !important;
	}

	:deep(.u-input__input) {
		color: #111111 !important;
		font-size: 30rpx !important;
		letter-spacing: 6rpx;
	}

	:deep(.u-input--focus) {
		border-color: #409EFF !important;
		/* 蓝色焦点 */
	}

	/* ===== 错误提示 ===== */
	.gc-tips {
		margin-top: 10rpx;
		min-height: 34rpx;
	}

	.gc-error {
		color: #E53935;
		font-size: 24rpx;
	}

	/* ===== 按钮 ===== */
	.gc-actions {
		margin-top: 24rpx;
		display: flex;
		gap: 16rpx;
	}

	/* 主按钮：蓝色（更像你截图） */
	:deep(.u-button--primary) {
		background: #409EFF !important;
		border: none !important;
		color: #FFFFFF !important;
		font-weight: 700 !important;
	}

	/* 次按钮 */
	:deep(.u-button--info) {
		background: #FFFFFF !important;
		border: 1rpx solid #DCDCDC !important;
		color: #555555 !important;
		font-weight: 600 !important;
	}
</style>
