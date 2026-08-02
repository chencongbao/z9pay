<template>
	<view class="content">
		<z-paging ref="paging" @query="fetchData" :hide-empty-view="true">
			<bob-header slot="top" />
			<view class="big-screen" v-if="show">
				<uni-row class="block">
					<uni-col :span="12" class="item" v-if="$store.state.agent == 0">
						<uni-card :isFull="true" :is-shadow="false" class="card3" margin="8rpx" padding="10rpx" :border="false" v-if="deposit_amount == 0">
							<view class="title">账户余额</view>
							<view class="value">
								{{ balance_amount }}
							</view>
						</uni-card>
						<uni-card :isFull="true" :is-shadow="false" class="card3" margin="8rpx" padding="10rpx" :border="false" v-else>
							<view class="title">充值剩余押金</view>
							<view class="value">
								{{ deposit_remaining_amount }}
							</view>
						</uni-card>
					</uni-col>
					<uni-col :span="12" class="item" v-if="$store.state.agent == 1">
						<uni-card :isFull="true" :is-shadow="false" class="card3" margin="8rpx" padding="10rpx" :border="false">
							<view class="title">我的金主</view>
							<view class="value">
								{{ user_number }}
							</view>
						</uni-card>
					</uni-col>
					<uni-col :span="12" class="item">
						<uni-card :isFull="true" :is-shadow="false" class="card4" margin="8rpx" padding="10rpx" :border="false" v-on:click="$navigate('/pages/balancelog/balancelog')">
							<view class="stitle">
								<view class="name">
									{{ $store.state.agent == 1 ? '账户余额' : '佣金余额' }}
								</view>
								<uni-icons type="right" color="#ffffff" size="20"></uni-icons>
							</view>
							<view class="value" v-if="$store.state.agent == 1">
								{{ balance_amount }}
							</view>
							<view class="value" v-else>
								{{ commission_balance_amount }}
							</view>
						</uni-card>
					</uni-col>
				</uni-row>
				<uni-row class="block">
					<uni-col :span="12" class="item">
						<uni-card :isFull="true" :is-shadow="false" class="card1" margin="8rpx" padding="10rpx" :border="false">
							<view class="title">当月总跑量</view>
							<view class="value">
								{{ total_amount }}
							</view>
						</uni-card>
					</uni-col>
					<uni-col :span="12" class="item">
						<uni-card :isFull="true" :is-shadow="false" class="card2" margin="8rpx" padding="10rpx" :border="false">
							<view class="title">今日总跑量</view>
							<view class="value">
								{{ today_amount }}
							</view>
						</uni-card>
					</uni-col>
				</uni-row>
			</view>

			<view class="table">
				<view class="item" v-for="(vo, index) in lists" v-bind:key="index">
					<uni-card :isFull="true" :border="false" :is-shadow="false" class="card" margin="0rpx" padding="0rpx">
						<view class="title" slot="title">
							<view class="date">
								{{ vo.date }}
							</view>
							<image src="../../static/img/img12.png" mode="" class="img"></image>
						</view>
						<view class="main">
							<view class="block">
								<view class="label">代收（跑量）</view>
								<view class="value">{{ vo.deposit_income }}({{ vo.deposit_order_total_amount }})</view>
							</view>
							<view class="block">
								<view class="label">代付（跑量）</view>
								<view class="value">{{ vo.transfer_income }}({{ vo.transfer_order_total_amount }})</view>
							</view>
							<view class="block">
								<view class="label">总收益</view>
								<view class="value">
									{{ vo.total_income }}
								</view>
							</view>
						</view>
					</uni-card>
				</view>
			</view>
		</z-paging>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				deposit_remaining_amount: 0,
				commission_balance_amount: 0,
				deposit_total_amount: 0,
				total_deposit_total_amount: 0,
				user_number: 0,
				balance_amount: 0,
				total_amount: 0,
				today_amount: 0,
				deposit_amount: 0,
				lists: [],
				show: false
			};
		},
		onLoad() {
			if (this.$store.state.agent == 1) {
				uni.setTabBarItem({ index: 1, visible: false });
				uni.setTabBarItem({ index: 2, visible: false });
				uni.setTabBarItem({ index: 3, visible: true });
				uni.setTabBarItem({ index: 4, visible: true });
			} else {
				uni.setTabBarItem({ index: 3, visible: false });
				uni.setTabBarItem({ index: 4, visible: false });
				uni.setTabBarItem({ index: 1, visible: true });
				uni.setTabBarItem({ index: 2, visible: true });
			}
		},
		methods: {
			fetchData(pageNo, pageSize) {
				this.$ajax.get('v2/users/index').then((res) => {
					if (res) {
						this.show = true;
						this.lists = res.data.lists;
						this.user_number = res.data.user_number;
						this.deposit_remaining_amount = res.data.deposit_remaining_amount;

						this.commission_balance_amount = res.data.commission_balance_amount;
						this.deposit_total_amount = res.data.deposit_total_amount;
						this.total_deposit_total_amount = res.data.total_deposit_total_amount;
						this.balance_amount = res.data.balance_amount;
						this.total_amount = res.data.total_amount;
						this.today_amount = res.data.today_amount;
						this.deposit_amount = res.data.deposit_amount;
					}
					this.$refs.paging.complete(true);
				});
			}
		}
	};
</script>

<style lang="less" scoped>
	.content {
		background-color: #1c1c1c !important;
		min-height: calc(100vh - 48px);

		::v-deep .z-paging-content-fixed {
			left: var(--window-left);
			right: var(--window-right);
		}

		.big-screen {
			padding: 20rpx;

			>.block {
				margin-bottom: 20rpx;

				>.item {
					>.card1 {
						background-color: rgba(250, 111, 102, 0.6) !important;
						border-color: rgba(250, 111, 102, 0.6) !important;
						padding: 0rpx !important;
						border-radius: 8rpx !important;
						margin-right: 10rpx !important;
					}

					>.card2 {
						background-color: rgba(255, 173, 79, 0.69) !important;
						border-color: rgba(255, 173, 79, 0.69) !important;
						padding: 0rpx !important;
						border-radius: 8rpx !important;
						margin-left: 10rpx !important;
					}

					>.card3 {
						background-color: rgba(106, 141, 251, 0.6) !important;
						border-color: rgba(106, 141, 251, 0.6) !important;
						padding: 0rpx !important;
						border-radius: 8rpx !important;
						margin-right: 10rpx !important;
					}

					>.card4 {
						background-color: rgba(51, 207, 185, 0.6) !important;
						border-color: rgba(51, 207, 185, 0.6) !important;
						padding: 0rpx !important;
						border-radius: 8rpx !important;
						margin-left: 10rpx !important;
					}

					.uni-card__content {
						display: flex;
						flex-direction: column;

						>.title {
							text-align: left;
							font-size: 28rpx;
							color: rgba(255, 255, 255, 0.7);
							padding-top: 16rpx;
							padding-bottom: 24rpx;
							padding-left: 10rpx;
						}

						>.stitle {
							text-align: left;
							font-size: 28rpx;
							color: rgba(255, 255, 255, 0.7);
							padding-top: 16rpx;
							padding-bottom: 24rpx;
							padding-left: 10rpx;
							display: flex;
							justify-content: space-between;
						}

						>.value {
							text-align: right;
							color: #ffffff;
							font-size: 44rpx;
							padding-top: 16rpx;
							padding-bottom: 24rpx;
							padding-right: 10rpx;
						}
					}
				}
			}
		}

		.table {
			padding: 20rpx;

			>.item {
				margin-bottom: 24rpx;

				>.card {
					border-radius: 8rpx !important;
					padding: 0rpx !important;
					background-color: #424242 !important;

					.title {
						display: flex;
						background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
						justify-content: space-between;
						align-items: center;
						padding-left: 32rpx;
						padding-right: 10rpx;

						>.date {
							padding-top: 8rpx !important;
							padding-bottom: 8rpx !important;
							color: rgb(66, 66, 66);
							font-size: 36rpx;
						}

						>.img {
							width: 70rpx;
							height: 70rpx;
						}
					}

					.main {
						padding: 16rpx;
						background-color: #424242 !important;
						display: flex;
						justify-content: space-around;

						>.block {
							flex: 1;
							flex-direction: column;
							justify-content: center;
							align-items: center;

							>.label {
								color: #c1c1c1;
								font-weight: 400;
								font-size: 28rpx;
								font-weight: normal;
								padding-bottom: 10rpx;
								text-align: center;
							}

							>.value {
								font-weight: normal;
								color: #ffffff;
								font-size: 32rpx;
								text-align: center;
							}
						}
					}
				}
			}
		}
	}
</style>
