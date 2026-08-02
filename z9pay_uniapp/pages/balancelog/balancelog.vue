<template>
	<view class="content">
		<z-paging ref="paging3" v-model="lists3" @query="fetchData">
			<view slot="top">
				<uni-nav-bar left-icon="arrow-left" title="收益明细" backgroundColor="#121212" :border="false" color="#edd185" height="96rpx" class="header" @clickLeft="$back()" left-width="40rpx"> </uni-nav-bar>
			</view>
			<view class="lists">
				<view class="item" v-for="(vo1, index1) in lists3" v-bind:key="index1">
					<view class="left-info">
						<view class="name">
							{{ vo1.new_order_type }}：{{ vo1.amount > 0 ? "+"+vo1.amount : vo1.amount }}
						</view>
						<view class="balance" v-if="$store.state.agent == 0">
							余额：{{vo1.type_balance_amount}}
						</view>
						<view class="balance" v-else>
							余额：{{vo1.balance_amount}}
						</view>
					</view>
					<view class="info">
						<view class="sinfo">
							<view class="amount" style="text-align: right;">
								创建时间
							</view>
							<view class="date">
								{{ vo1.create_time }}
							</view>
						</view>
					</view>
				</view>
			</view>
		</z-paging>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				lists3: []
			};
		},
		methods: {
			fetchData(pageNo, pageSize) {
				this.$ajax.get(
					'v2/users/balanceLogIndex',
					{ page: pageNo }
				).then((res) => {
					if (res) {
						this.$refs.paging3.complete(res.data.lists.lists);
					} else {
						this.$refs.paging3.complete(false);
					}
				});
			}
		}
	};
</script>

<style lang="less" scoped>
	.content {
		.header {
			::v-deep .uni-nav-bar-text {
				font-size: 32rpx;
			}

			::v-deep .uni-navbar__header-container-inner {
				justify-content: flex-start;
			}

			::v-deep .uni-icons {
				font-size: 50rpx !important;
			}

			.header_right {
				background-color: rgb(237, 209, 133);
				width: 20rpx;
				height: 20rpx;
				display: flex;
				justify-content: center;
				align-items: center;
				border-radius: 4rpx;
				padding: 10rpx;

				>.icon {
					font-size: 32rpx !important;
				}
			}

			.right {
				display: flex;
				justify-content: flex-end;
				align-items: center;

				>.img {
					width: 36rpx;
					height: 36rpx;
				}

				>.name {
					font-size: 28rpx;
					color: #edd185;
					padding-left: 10rpx;
				}
			}
		}

		.lists {
			margin-top: 20rpx;

			>.item {
				display: flex;
				background-color: #424242;
				color: #fff;
				border-radius: 8rpx;
				padding: 32rpx;
				margin-bottom: 20rpx;
				margin-left: 20rpx;
				margin-right: 20rpx;

				>.left-info {
					flex: 1;
					display: flex;
					flex-direction: column;
					color: hsla(0, 0%, 100%, .7);
					align-self: center;

					>.name {
						font-size: 28rpx;
					}

					>.balance {
						padding-top: 10rpx;
						font-size: 24rpx;
						color: #e8bd70;
					}
				}



				>.info {
					flex: 1;
					display: flex;
					font-size: 24rpx;
					color: hsla(0, 0%, 100%, .7);
					justify-content: flex-end;
					align-items: center;

					>.sinfo {
						display: flex;
						flex-direction: column;
						justify-content: flex-end;
						align-items: flex-end;
						flex: 1;

						>.amount {
							padding-bottom: 10rpx;
							display: flex;
							justify-content: flex-end;
							align-items: center;

							>.text {
								padding-left: 10rpx;
							}
						}
					}
				}
			}
		}
	}
</style>
