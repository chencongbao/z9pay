<template>
	<view class="bob_header">
		<uni-nav-bar backgroundColor="#121212" :border="false" leftWidth="300rpx" rightWidth="300rpx" height="96rpx" :fixed="true">
			<view slot="left" class="logo">
				<image v-bind:src="'/static/img/'+$logo" mode="" class="img"></image>
				<view class="name"> {{$appName}} </view>
			</view>
			<view slot="right" class="username" @click="$refs.showRight.open()">
				<div class="block">
					<uni-icons type="person-filled" color="#edd185" size="40rpx" class="icon1" v-if="$store.state.agent == 0"></uni-icons>
					<uni-icons custom-prefix="iconfont" type="icon-dailiren" color="#edd185" size="40rpx" class="icon1" v-else></uni-icons>
					<view class="nickname">
						{{ $store.state.username }}
					</view>
					<uni-icons custom-prefix="iconfont" type="icon-wifi" color="#edd185" size="24rpx" class="icon2"></uni-icons>
				</div>
			</view>
		</uni-nav-bar>

		<uni-drawer ref="showRight" mode="right" class="drawer">
			<view class="scroll-view">
				<scroll-view class="scroll-view-box" scroll-y="true">
					<view class="user-info">
						<view class="header">
							<uni-icons type="person-filled" color="#000000" size="40" class="icon1" v-if="$store.state.agent == 0"></uni-icons>
							<text v-else class="agent">代</text>
						</view>
						<view class="nickname"> {{ $store.state.username }}(#{{ $store.state.userid }}) </view>
					</view>
					<view class="status" v-if="$store.state.agent == 0">
						<switch :checked="$store.state.status" disabled style="transform: scale(0.8)" color="#EDD185" />
						<view class="name" v-if="$store.state.status == 1"> 已开启接单 </view>
						<view class="name" v-else> 已停止接单 </view>
					</view>
					<view class="status" v-if="$store.state.agent == 0">
						<switch :checked="$store.state.transfer_notice == 1 ? true : false" :disabled="transferNoticeUpdating" style="transform: scale(0.8)" color="#EDD185" @change="bindTransferNotice" />
						<view class="name"> 代付通知</view>
					</view>
					<view class="status" v-if="$store.state.agent == 0">
						<switch :checked="$store.state.deposit_notice == 1 ? true :false" :disabled="depositNoticeUpdating" style="transform: scale(0.8)" color="#EDD185" @change="bindDepositNotice" />
						<view class="name"> 代收通知</view>
					</view>
					<view class="status" v-if="$store.state.agent == 0">
						<switch :checked="$store.state.auto_refresh == 1 ? true :false" :disabled="autoRefreshUpdating" style="transform: scale(0.8)" color="#EDD185" @change="bindAutoRefresh" />
						<view class="name"> 自动刷新</view>
					</view>
					<view class="menus">
						<navigator class="mitem" url="/pages/bank/bank" v-if="$store.state.agent == 0 && $store.state.self_add_bank == 1">
							<view class="label">
								<view class="icon">
									<image src="../../static/img/img13.png" mode="" class="img"></image>
								</view>
								<view class="name"> 收款卡管理 </view>
							</view>
						</navigator>
						<navigator class="mitem" url="/pages/team/bank/bank" v-if="$store.state.agent == 1 && $store.state.self_add_bank == 1">
							<view class="label">
								<view class="icon">
									<image src="../../static/img/img13.png" mode="" class="img"></image>
								</view>
								<view class="name"> 收款卡管理 </view>
							</view>
						</navigator>
						<view class="mitem" v-on:click="$refs.passwordPopup.open()">
							<view class="label">
								<view class="icon">
									<image src="../../static/img/img4.png" mode="" class="img"></image>
								</view>
								<view class="name"> 修改密码 </view>
							</view>
						</view>
						<view class="mitem" v-on:click="logout">
							<view class="label">
								<view class="icon">
									<image src="../../static/img/img5.png" mode="" class="img"></image>
								</view>
								<view class="name"> 退出登录 </view>
							</view>
						</view>
					</view>
				</scroll-view>
			</view>
		</uni-drawer>

		<uni-popup ref="passwordPopup" type="top">
			<view class="password">
				<view class="header">
					<view class="close" v-on:click="$refs.passwordPopup.close()">
						<uni-icons type="closeempty" color="#1c1c1c" size="28"></uni-icons>
					</view>
					<view class="name"> 修改密码 </view>
				</view>
				<view class="forms">
					<view class="item">
						<BobInput label="旧密码" type="password" v-model="form.old_password" />
					</view>
					<view class="item">
						<BobInput label="新密码" type="password" v-model="form.password" />
					</view>
					<view class="item">
						<BobInput label="确认新密码" type="password" v-model="form.password_confirmation" />
					</view>
					<u-button iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" text="提交" class="btn" :loading="passwordSubmitting" :disabled="passwordSubmitting" v-on:click="submit"></u-button>
				</view>
			</view>
		</uni-popup>
	</view>
</template>

<script>
	import { logout } from "@/static/js/funtions";
	import { getLogin, getName, getUsername, getUserid, getStatus, getAgent, getDepositNotice, getTransferNotice, getAutoRefresh } from "@/static/js/funtions";
	export default {
		name: "bob_header",
		data() {
			return {
				name: "",
				sitem1: false,
				passwordSubmitting: false,
				transferNoticeUpdating: false,
				depositNoticeUpdating: false,
				autoRefreshUpdating: false,
				form: {
					old_password: "",
					password: "",
					password_confirmation: "",
				},
			};
		},
		methods: {
			submit() {
				if (this.passwordSubmitting) return;
				this.passwordSubmitting = true;
				this.$ajax.post("v2/users/updatePassword", this.form).then((res) => {
					if (!res) return;
					this.form.old_password = "";
					this.form.password = "";
					this.form.password_confirmation = "";
					this.$refs.passwordPopup.close();
				}).finally(() => {
					this.passwordSubmitting = false;
				});
			},
			bindAutoRefresh(e) {
				if (this.autoRefreshUpdating) return;
				this.autoRefreshUpdating = true;
				this.$ajax.get("v2/users/setAutoRefresh").then(res => {
					if (res) {
						uni.setStorageSync("auto_refresh", res.data.auto_refresh);
						this.$store.commit("setAutoRefresh", getAutoRefresh());
					}
				}).finally(() => {
					this.autoRefreshUpdating = false;
				});
			},
			bindTransferNotice(e) {
				if (this.transferNoticeUpdating) return;
				this.transferNoticeUpdating = true;
				this.$ajax.get("v2/users/setTransferNotice").then(res => {
					if (res) {
						uni.setStorageSync("transfer_notice", res.data.transfer_notice);
						this.$store.commit("setTransferNotice", getTransferNotice());
						if (getTransferNotice() == 1) {
							this.$store.commit("watchTransferNotice", getTransferNotice());
							if (this.$store.state.default_voice) {
								var bgAudio = new audioController();
								bgAudio.play(this.$store.state.default_voice);
							}
						}
						if (getTransferNotice() == 0) {
							this.$store.commit("leaveTransferNotice");
						}
					}
				}).finally(() => {
					this.transferNoticeUpdating = false;
				});
			},
			bindDepositNotice(e) {
				if (this.depositNoticeUpdating) return;
				this.depositNoticeUpdating = true;
				this.$ajax.get("v2/users/setDepositNotice").then(res => {
					if (res) {
						uni.setStorageSync("deposit_notice", res.data.deposit_notice);
						this.$store.commit("setDepositNotice", getDepositNotice());
						if (getDepositNotice() == 1) {
							this.$store.commit("watchDepositNotice", getDepositNotice());
							if (this.$store.state.default_voice) {
								var bgAudio = new audioController();
								bgAudio.play(this.$store.state.default_voice);
							}
						}
						if (getDepositNotice() == 0) {
							this.$store.commit("leaveDepositNotice");
						}
					}
				}).finally(() => {
					this.depositNoticeUpdating = false;
				});
			},
			logout() {
				logout();
				this.$store.commit("setLogin", false);
				this.$store.commit("leaveTransferNotice");
				this.$store.commit("leaveDepositNotice");
				uni.reLaunch({
					url: "/pages/login/login",
				});
			},
		},
	};
</script>

<style lang="less" scoped>
	.bob_header {
		.logo {
			display: flex;
			justify-content: center;
			align-items: center;

			>.img {
				width: 50rpx;
				height: 50rpx;
			}

			>.name {
				padding-left: 20rpx;
				flex: 1;
				color: #edd185;
				font-size: 32rpx;
			}
		}

		.username {
			max-width: 300rpx;

			>.block {
				height: 48rpx;
				border: 2rpx solid rgb(60, 54, 39);
				display: flex;
				justify-content: center;
				color: #e8bd70;
				align-items: center;
				overflow: hidden;
				max-width: 290rpx;

				>.icon1 {
					width: 70rpx;
				}

				>.nickname {
					font-size: 28rpx;
					overflow: hidden;
					white-space: nowrap;
					word-break: keep-all;
					flex: 1;
					color: #edd185;
				}

				>.icon2 {
					padding-top: 4rpx;
					padding-left: 10rpx;
					align-self: center;
					width: 40rpx;
				}
			}
		}

		>.drawer {
			::v-deep .uni-drawer__content {
				width: 50% !important;
				background-color: #121212;
			}

			::v-deep .uni-drawer--right {
				right: var(--window-right);
			}

			.scroll-view {
				.user-info {
					padding: 32rpx 32rpx 0rpx 32rpx;
					display: flex;
					justify-content: flex-start;
					align-items: center;

					>.header {
						background-color: #edd185;
						width: 80rpx;
						height: 80rpx;
						border-radius: 50%;
						display: flex;
						justify-content: center;
						align-items: center;

						>.agent {
							font-weight: 800;
						}
					}

					>.nickname {
						color: #ffffff;
						font-size: 32rpx;
						padding-left: 20rpx;
					}
				}

				.status {
					padding-left: 32rpx;
					padding-right: 32rpx;
					padding-top: 64rpx;
					display: flex;
					justify-content: flex-start;
					align-items: center;
					border-bottom: 2rpx solid hsla(0, 0%, 100%, 0.12);
					padding-bottom: 32rpx;

					>.name {
						color: hsla(0, 0%, 100%, 0.7);
						font-size: 28rpx;
					}
				}

				.menus {
					padding: 32rpx 32rpx;
					margin-top: 10rpx;

					>.mitem {
						height: 116rpx;
						display: flex;
						justify-content: space-between;
						align-items: center;

						>.label {
							display: flex;
							justify-content: flex-start;
							align-items: center;
							flex: 1;

							>.icon {
								align-self: center;
								width: 52rpx;
								height: 52rpx;

								>.img {
									width: 52rpx;
									height: 52rpx;
								}
							}

							>.name {
								align-self: center;
								padding-left: 64rpx;
								color: #ffffff;
								font-size: 28rpx;
							}
						}

						>.icon {
							width: 52rpx;
							display: flex;
							justify-content: center;
							align-items: center;
						}

						&.active {
							>.icon {
								transform: rotate(180deg);
							}

							>.label {
								>.name {
									color: rgb(237, 209, 133);
								}
							}
						}
					}

					.block {
						>.mitem {
							height: 116rpx;
							display: flex;
							justify-content: space-between;
							align-items: center;

							>.label {
								display: flex;
								justify-content: flex-start;
								align-items: center;

								>.icon {
									width: 52rpx;

									>.img {
										width: 52rpx;
										height: 52rpx;
									}
								}

								>.name {
									padding-left: 64rpx;
									color: #ffffff;
									font-size: 28rpx;
								}
							}

							>.icon {
								width: 52rpx;
								display: flex;
								justify-content: center;
								align-items: center;
								height: 52rpx;

								>.img {
									width: 52rpx;
									height: 52rpx;
								}
							}
						}
					}
				}
			}
		}

		::v-deep .uni-popup {
			z-index: 999;
		}

		.password {
			width: 100%;
			background-color: #424242;
			min-height: 100vh;

			>.header {
				height: 96rpx;
				background-color: #edd185;
				color: rgba(0, 0, 0, 0.87);
				display: flex;
				justify-content: flex-start;
				align-items: center;
				padding-left: 32rpx;

				>.name {
					padding-left: 64rpx;
				}
			}

			>.forms {
				padding: 80rpx 20rpx;

				>.item {
					margin-bottom: 60rpx;
				}
			}
		}
	}
</style>
