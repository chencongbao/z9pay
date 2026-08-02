<template>
	<view class="content">
		<z-paging ref="paging" v-model="lists" @query="fetchData">
			<view slot="top">
				<bob-header />
				<bob-tab :menus="menus" v-on:tab="clickTab" />
				<BobSearch placeholder="账号/编号/名称" v-on:search="search" v-on:reset="reset" v-on:submit="submit" v-model="form.username">
					<view class="search">
						<view class="name">
							启用状态
						</view>
						<view class="values">
							<view class="tag" v-for="(vo1,index1) in status1List" v-bind:key="index1" v-bind:class="{'active':vo1.id == form.status}" v-on:click="selectStatus(vo1)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo1.name}}</span>
							</view>
						</view>
					</view>
				</BobSearch>
			</view>

			<view class="main">
				<view class="lists">
					<view class="item" v-bind:class="{'active':vo.show}" v-for="(vo,index) in lists" v-bind:key="index">
						<view class="top-info">
							<view class="top-left-info">
								<view class="label">
									<uni-icons type="person-filled" color="#121212" size="50rpx" class="icon1" v-if="vo.is_agent == 0"></uni-icons>
									<uni-icons custom-prefix="iconfont" type="icon-dailiren" color="#121212" size="50rpx" class="icon1" v-else></uni-icons>
									<view class="name">
										{{vo.bname}}
									</view>
								</view>
							</view>
							<view class="top-right-info">
								<view class="top-right-main">
									<view class="status">
										{{vo.status_text}}
									</view>
								</view>
							</view>
						</view>
						<template v-if="form.type == 1">
							<view class="middle-info">
								<view class="rows">
									<view class="item">
										<view class="name">
											今日代付跑量
										</view>
										<view class="value">
											{{vo.today_transfer_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代付交易数
										</view>
										<view class="value">
											{{vo.today_transfer_number}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代付收益
										</view>
										<view class="value">
											{{vo.today_transfer_income}}
										</view>
									</view>
								</view>
								<view class="rows">
									<view class="item">
										<view class="name">
											今日代收跑量
										</view>
										<view class="value">
											{{vo.today_deposit_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代收交易数
										</view>
										<view class="value">
											{{vo.today_deposit_number}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代收收益
										</view>
										<view class="value">
											{{vo.today_deposit_income}}
										</view>
									</view>
								</view>
								<view class="rows">
									<view class="item">
										<view class="name">
											当月总跑量
										</view>
										<view class="value">
											{{vo.total_deposit_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											已开启收款卡
										</view>
										<view class="value">
											{{vo.bank_count}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											剩余押金
										</view>
										<view class="value">
											{{vo.remaining_deposit}}
										</view>
									</view>
								</view>
							</view>
						</template>
						<template v-if="form.type == 2">
							<view class="middle-info">
								<view class="rows">
									<view class="item">
										<view class="name">
											今日代付跑量
										</view>
										<view class="value">
											{{vo.today_transfer_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代付交易数
										</view>
										<view class="value">
											{{vo.today_transfer_number}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代付收益
										</view>
										<view class="value">
											{{vo.today_transfer_income}}
										</view>
									</view>
								</view>
								<view class="rows">
									<view class="item">
										<view class="name">
											今日代收跑量
										</view>
										<view class="value">
											{{vo.today_deposit_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代收交易数
										</view>
										<view class="value">
											{{vo.today_deposit_number}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											今日代收收益
										</view>
										<view class="value">
											{{vo.today_deposit_income}}
										</view>
									</view>
								</view>
								<view class="rows">
									<view class="item">
										<view class="name">
											当月总跑量
										</view>
										<view class="value">
											{{vo.total_deposit_amount}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											金主数量
										</view>
										<view class="value">
											{{vo.user_count}}
										</view>
									</view>
									<view class="item">
										<view class="name">
											已开启收款卡
										</view>
										<view class="value">
											{{vo.bank_count}}
										</view>
									</view>
								</view>
							</view>
						</template>
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
				lists: [],
				menus: [{
					id: 1,
					name: "我的金主",
					active: 1
				}, {
					id: 2,
					name: "我的代理",
					active: 0
				}],
				status1List: [{
					id: 0,
					name: "禁用"
				}, {
					id: 1,
					name: "启用"
				}],
				form: {
					type: 1,
					status: -1,
					username: ""
				}
			}
		},
		methods: {
			clickTab(tab) {
				this.form.type = tab;
				this.fetchData();
			},
			fetchData(pageNo, pageSize) {
				this.$ajax.get("v2/users/teamUserIndex", Object.assign({
					page: pageNo
				}, this.form)).then(res => {
					if (res) {
						this.$refs.paging.complete(res.data.lists.lists);
					}
				});
			},
			open(index) {
				this.$set(this.lists[index], "show", this.lists[index].show == 1 ? 0 : 1);
			},
			selectStatus(item) {
				this.form.status = item.id;
			},
			search() {
				this.$refs.paging.reload();
			},
			reset() {
				this.form = {
					type: this.form.type,
					status: -1,
					username: ""
				};
				this.$refs.paging.reload();
			},
			submit() {
				this.$refs.paging.reload();
			}
		}
	}
</script>

<style lang="less" scoped>
	.content {
		min-height: 100vh;
		background-color: #1c1c1c !important;



		::v-deep .uni-popup {
			z-index: 999;
		}

		.password {
			width: 100%;
			height: 100vh;
			background-color: #424242;

			>.header {
				height: 96rpx;
				background-color: #EDD185;
				color: rgba(0, 0, 0, .87);
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


		.main {
			margin: 20rpx;
			width: 750rpx;



			>.lists {
				margin-top: 20rpx;

				>.item {
					display: flex;
					flex-direction: column;
					margin-bottom: 32rpx;

					>.top-info {
						border-top-left-radius: 24rpx;
						border-top-right-radius: 24rpx;
						background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
						display: flex;
						padding: 32rpx;
						color: rgba(0, 0, 0, 0.87);

						>.top-left-info {
							display: flex;
							flex-direction: column;
							flex: 2;

							>.label {
								display: flex;
								justify-content: flex-start;
								align-items: center;

								>.img {
									width: 40rpx;
									height: 40rpx;
								}

								>.name {
									font-weight: 900;
									font-size: 32rpx;
									color: rgba(0, 0, 0, 0.87);
								}
							}

							>.rate {
								font-size: 28rpx;

								>.value {
									padding-left: 10rpx;
									font-size: 40rpx;
								}
							}

							>.nickname {
								font-size: 28rpx;

								>.value {
									padding-left: 10rpx;
								}
							}
						}

						>.top-right-info {
							display: flex;
							font-size: 24rpx;
							justify-content: flex-start;
							align-items: center;
							flex-direction: column;

							>.top-right-main {
								display: flex;
								flex-direction: column;

								>.name {
									text-align: center;
								}

								>.value {
									text-align: center;
								}

								>.status {
									border-radius: 32rpx;
									color: #fffff4;
									width: 110rpx;
									height: 64rpx;
									background-color: #121212;
									font-size: 28rpx;
									display: flex;
									justify-content: center;
									align-items: center;
								}
							}

							>.img {
								width: 48rpx;
								height: 48rpx;
							}
						}
					}

					>.middle-info {
						background-color: #272727;
						padding: 20rpx 0;
						font-size: 24rpx;
						border-bottom-left-radius: 24rpx;
						border-bottom-right-radius: 24rpx;

						>.rows {
							display: flex;

							>.item {
								flex: 1;
								display: flex;
								flex-direction: column;
								justify-content: center;
								align-items: center;
								padding-bottom: 20rpx;

								>.name {
									color: #c1c1c1;
								}

								>.value {
									color: #ffffff;
								}
							}
						}
					}

					>.bottom-info {
						background-color: #424242;
						border-bottom-left-radius: 24rpx;
						border-bottom-right-radius: 24rpx;
						font-size: 24rpx;
						padding-top: 20rpx;
						padding-bottom: 20rpx;
						display: none;

						>.rows {
							display: flex;

							>.item {
								flex: 1;
								display: flex;
								flex-direction: column;
								justify-content: flex-start;
								align-items: center;

								>.name {
									color: #c1c1c1;
								}

								>.value {
									color: #ffffff;
								}
							}
						}
					}

					&.active {
						>.top-info {
							>.top-right-info {
								>.img {
									transform: rotate(180deg);
								}
							}
						}

						>.middle-info {
							border-bottom-left-radius: 0rpx;
							border-bottom-right-radius: 0rpx;
						}

						>.bottom-info {
							display: block;
						}
					}
				}
			}
		}


	}
</style>
