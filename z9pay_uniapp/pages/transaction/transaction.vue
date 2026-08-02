<template>
	<view class="content">
		<bob-header />
		<bob-tab :menus="menus" v-on:tab="clickTab" />
		<view class="block1" v-show="tab == 1">
			<z-paging ref="paging" v-model="lists" @query="fetchData" :fixed="false" :use-page-scroll="false">
				<bob-search slot="top" v-on:search="search1" v-on:reset="reset1" v-on:submit="submit1" v-model="form1.ordernumber">
					<view class="search">
						<view class="name">订单状态</view>
						<view class="values">
							<view class="tag" v-for="(vo1, index1) in status1List" v-bind:key="index1" v-bind:class="{ active: vo1.id == form1.status }" v-on:click="selectStatus1(vo1)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{ vo1.name }}</span>
							</view>
						</view>
						<view class="name">成交时间段</view>
						<view class="values">
							<view class="tag" v-for="(vo2, index2) in time1List" v-bind:key="index2" v-bind:class="{ active: vo2.id == form1.time }" v-on:click="selectTime1(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{ vo2.name }}</span>
							</view>
						</view>
					</view>
				</bob-search>
				<view class="lists">
					<view class="item" v-for="(vo, index) in lists" v-bind:key="index" v-bind:class="{ overtime: vo.status == 4, fail: vo.status == 6, confirm: vo.status == 3 || vo.status == 7 }" v-on:click="detail(vo)">
						<view class="icon">
							<IconSvg name="succeeded" :color="vo.color" width="48" height="48" />
							<view class="text">
								{{ vo.status_text }}
							</view>
						</view>
						<view class="amount">
							<view class="money">
								{{ vo.amount }}
							</view>
							<view class="info">
								<view class="sinfo" v-on:click.stop="$copy(vo.pay_name)">
									付款人：{{ vo.pay_name }}
									<uni-icons type="icon-fuzhi" custom-prefix="iconfont" color="#424242" class="icon"></uni-icons>
								</view>
							</view>
						</view>
						<view class="info" v-if="vo.status == 3 || vo.status == 7">
							<view class="sinfo">
								<view class="amount" style="text-align: right">
									{{ vo.pay_info }}
								</view>
								<view class="date">
									{{ vo.create_time }}
								</view>
							</view>
							<view class="btn" v-on:click.stop="confirmPayButton(vo, index)">
								<u-button type="primary" :plain="true" text="确认到账"></u-button>
							</view>
						</view>
						<view class="info" v-else>
							<view class="sinfo">
								<view class="amount" style="text-align: right">
									{{ vo.pay_info }}
								</view>
								<view class="date">
									{{ vo.create_time }}
								</view>
							</view>
							<uni-icons type="right" color="#ffffff" size="20"></uni-icons>
						</view>
					</view>
				</view>
			</z-paging>
		</view>
		<view class="block2" v-show="tab == 2">
			<z-paging ref="paging1" v-model="lists1" @query="fetchData1" :fixed="false" :use-page-scroll="false">
				<bob-search slot="top" v-on:search="search2" v-on:reset="reset2" v-on:submit="submit2" v-model="form2.ordernumber">
					<view class="search">
						<view class="name">成交时间段</view>
						<view class="values">
							<view class="tag" v-for="(vo2, index2) in time1List" v-bind:key="index2" v-bind:class="{ active: vo2.id == form2.time }" v-on:click="selectTime2(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{ vo2.name }}</span>
							</view>
						</view>
					</view>
				</bob-search>
				<view class="lists">
					<view class="item" v-for="(vo, index) in lists1" v-bind:key="index">
						<view class="left-info">
							<view class="name">代收金额</view>
							<view class="amount">
								{{ vo.amount }}
							</view>
						</view>
						<view class="right-info">
							<view class="sinfo">
								<view class="row amount">
									<span>收益:</span>
									<span>{{ vo.user_commission }}</span>
								</view>
								<view class="row date">
									{{ vo.time }}
								</view>
								<view class="row text">
									{{ vo.ordernumber }}
								</view>
							</view>
						</view>
					</view>
				</view>
			</z-paging>
		</view>

		<BobDetail ref="order_detail" :rows="rows"></BobDetail>

		<BobDetail title="核实到账信息" ref="confirm_order" class="confirm-order" v-if="!$isempty(row)">
			<view class="item">
				<view class="label">订单号</view>
				<view class="value">
					{{ row.ordernumber }}
				</view>
			</view>
			<view class="item">
				<view class="label">账号信息</view>
				<view class="value">
					{{ row.pay_info }}
				</view>
			</view>
			<view class="item">
				<view class="label">订单金额</view>
				<view class="value">
					{{ row.amount }}
				</view>
			</view>
			<view class="item">
				<view class="label">创建时间</view>
				<view class="value">
					{{ row.time }}
				</view>
			</view>
			<view class="amount">
				<view class="name">请输入实际入账金额</view>
				<view class="inputs">
					<input type="text" class="input" v-model="form.amount" />
				</view>
			</view>
			<view class="footer" slot="footer">
				<view class="submit">
					<u-button text="确认" throttleTime="1000" v-on:click="submitPay"></u-button>
				</view>
				<view class="canncel" v-on:click="canncelPay">
					<u-button text="取消"></u-button>
				</view>
			</view>
		</BobDetail>
		<u-notify ref="uNotify" class="tip" message="您有新的代收订单，请手动刷新订单"></u-notify>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				rows: [],
				row: {},
				form: {
					index: 0,
					order_id: 0,
					amount: ''
				},
				menus: [{
						id: 1,
						name: '代收列表',
						active: 1
					},
					{
						id: 2,
						name: '交易流水',
						active: 0
					}
				],
				tab: 1,
				lists: [],
				lists1: [],
				status1List: [{
						id: 5,
						name: '成功'
					},
					{
						id: 3,
						name: '待支付'
					},
					{
						id: 4,
						name: '超时'
					},
					{
						id: 6,
						name: '失败'
					},
					{
						id: 7,
						name: '待确认'
					}
				],
				time1List: [{
						id: 1,
						name: '今天'
					},
					{
						id: 2,
						name: '所有'
					}
				],
				form1: {
					status: 0,
					time: 1,
					ordernumber: ''
				},
				form2: {
					time: 1,
					ordernumber: ''
				},
				depositOrderHandler: null,
				depositRefreshTimer: null
			};
		},
		onLoad() {
			this.depositOrderHandler = (event) => {
				this.$refs.uNotify.show({
					top: 2,
					type: 'warning',
					message: '您有新的代收订单，请手动刷新订单',
					duration: 0,
					fontSize: 28
				});
				if (this.$store.state.auto_refresh == 1 && this.tab == 1) {
					if (this.depositRefreshTimer) clearTimeout(this.depositRefreshTimer);
					this.depositRefreshTimer = setTimeout(() => {
						this.depositRefreshTimer = null;
						this.reset1();
					}, 1000);
				}
			};
			uni.$on('depositOrder', this.depositOrderHandler);
		},
		onUnload() {
			if (this.depositOrderHandler) uni.$off('depositOrder', this.depositOrderHandler);
			if (this.depositRefreshTimer) clearTimeout(this.depositRefreshTimer);
		},
		methods: {
			clickTab(tab) {
				this.tab = tab;
			},
			detail(vo) {
				let data = [];
				data.push({
					name: '订单状态',
					value: vo.status_text
				});
				if (vo.pay_status == 2) {
					data.push({
						name: '付款状态',
						value: '付方已确认'
					});
					if (vo.pay_certificate) {
						data.push({
							name: '付款凭证',
							value: vo.pay_certificate,
							image: true
						});
					}
				}
				if (vo.pay_status == 3) {
					data.push({
						name: '付款状态',
						value: '付方已取消'
					});
				}
				data.push({
					name: '订单号',
					value: vo.ordernumber,
					copy: true
				});
				data.push({
					name: '银行账号',
					value: vo.pay_info,
					copy: true
				});
				data.push({
					name: '订单金额',
					value: vo.amount
				});
				data.push({
					name: '实际金额',
					value: vo.actual_amount
				});
				if (vo.status == 5) {
					data.push({
						name: '收益',
						value: vo.user_commission
					});
				}
				data.push({
					name: '创建时间',
					value: vo.create_time
				});
				if (vo.status == 5) {
					data.push({
						name: '成交时间',
						value: vo.time
					});
				}
				this.rows = data;
				this.$refs.order_detail.open();
			},
			confirmPayButton(vo, index) {
				this.row = vo;
				this.form.amount = vo.amount;
				this.form.order_id = vo.id;
				this.form.index = index;
				this.$nextTick(() => {
					this.$refs.confirm_order.open();
				});
			},
			submitPay() {
				this.$refs.confirm_order.close();
				this.$ajax.post('v2/deposit-orders/confirmPay', this.form).then((res) => {
					if (res) {
						this.$set(this.lists[this.form.index], 'status', 5);
						this.$set(this.lists[this.form.index], 'status_text', '成功');
						this.$set(this.lists[this.form.index], 'color', '#4caf50');
					}
				});
			},
			canncelPay() {
				this.$refs.confirm_order.close();
			},
			fetchData(pageNo, pageSize) {
				this.$ajax
					.get(
						'v2/deposit-orders/index',
						Object.assign({
								page: pageNo
							},
							this.form1
						)
					)
					.then((res) => {
						this.$refs.uNotify.close();
						if (res) {
							this.$refs.paging.complete(res.data.lists.lists);
						}
					});
			},
			fetchData1(pageNo, pageSize) {
				this.$ajax
					.get(
						'v2/deposit-orders/logs',
						Object.assign({
								page: pageNo
							},
							this.form2
						)
					)
					.then((res) => {
						if (res) {
							this.$refs.paging1.complete(res.data.lists.lists);
						}
					});
			},
			selectStatus1(item) {
				this.form1.status = item.id;
			},
			selectTime1(item) {
				this.form1.time = item.id;
			},
			search1() {
				this.$refs.paging.reload();
			},
			reset1() {
				this.form1 = {
					status: 0,
					time: 1,
					ordernumber: ''
				};
				this.$refs.paging.reload();
			},
			submit1() {
				this.$refs.paging.reload();
			},
			search2() {
				this.$refs.paging1.reload();
			},
			reset2() {
				this.form2 = {
					time: 1,
					ordernumber: ''
				};
				this.$refs.paging1.reload();
			},
			submit2() {
				this.$refs.paging1.reload();
			},
			selectTime2(item) {
				this.form2.time = item.id;
			}
		}
	};
</script>

<style lang="less" scoped>
	.content {
		background-color: #1c1c1c !important;
		min-height: 100vh;

		.tip {
			::v-deep(.u-notify) {
				padding: 8px 10px;
			}
		}

		.confirm-order {
			.amount {
				>.name {
					padding-top: 32rpx;
					color: #edd185;
					padding-bottom: 10rpx;
				}

				>.inputs {
					height: 80rpx;
					border-radius: 8rpx;
					border: 2rpx solid rgb(118, 118, 118);
					padding: 0rpx 32rpx;

					>.input {
						width: 100%;
						height: 80rpx;
						border: none;
						outline: none;
						color: #edd185;
						font-size: 40rpx;
						text-align: center;
					}
				}
			}

			.footer {
				display: flex;
				padding-top: 32rpx;
				justify-content: flex-end;

				>.submit {
					width: 128rpx;

					::v-deep .u-button--info {
						color: #edd185;
						background-color: #212121 !important;
						border-color: #212121 !important;
					}

					::v-deep .u-button {
						height: 72rpx !important;
						border-radius: 8rpx !important;
					}
				}

				>.canncel {
					width: 128rpx;
					margin-left: 32rpx;

					::v-deep .u-button--info {
						color: #edd185;
						background-color: #212121 !important;
						border-color: #212121 !important;
					}

					::v-deep .u-button {
						height: 72rpx !important;
						border-radius: 8rpx !important;
					}
				}
			}
		}

		.sitem-detail {
			width: 750rpx;

			::v-deep .uni-card {
				background-color: #424242 !important;
				border-color: #424242 !important;
			}

			::v-deep .uni-card .uni-card__header {
				border-bottom: 2rpx hsla(0, 0%, 100%, 0.12) solid !important;
			}

			::v-deep .uni-card .uni-card__header .uni-card__header-content .uni-card__header-content-title {
				font-size: 40rpx !important;
				color: #e8bd70 !important;
			}

			.card-content {
				padding: 10rpx;
				color: hsla(0, 0%, 100%, 0.7);
				font-size: 24rpx;

				>.item {
					display: flex;
					justify-content: space-between;
					padding-bottom: 10rpx;

					>.value {
						&.copy {
							>.span {
								padding-right: 10rpx;
							}

							display: flex;
						}
					}
				}

				>.btn {
					color: #424242 !important;
					margin-top: 32rpx;
				}
			}
		}

		.block1 {
			margin-top: 20rpx;
			margin-left: 20rpx;
			margin-right: 20rpx;
			position: relative;

			::v-deep .z-paging-content {
				height: calc(100vh - 96rpx - 86rpx - 100rpx);
			}

			::v-deep .zp-page-top {
				position: absolute;
			}

			.search {
				>.name {
					font-size: 32rpx;
					color: #ffffff;
				}

				>.values {
					padding-top: 20rpx;

					>.tag {
						display: inline-block;
						border: 2rpx solid hsla(0, 0%, 100%, 0.12);
						color: #fff;
						border-radius: 32rpx;
						font-size: 28rpx;
						padding: 10rpx 20rpx;
						margin-right: 20rpx;
						margin-bottom: 20rpx;

						>.icon {
							display: none;
						}

						&.active {
							background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
							display: inline-flex;
							color: #424242;
							justify-content: center;
							align-items: center;

							>.icon {
								display: inline-block;
							}

							>span {
								padding-left: 10rpx;
							}
						}
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
					padding: 32rpx 8rpx;
					margin-bottom: 20rpx;

					>.icon {
						display: flex;
						flex-direction: column;
						justify-content: center;
						align-items: center;
						width: 80rpx;

						>.text {
							font-size: 24rpx;
							color: #4caf50;
						}
					}

					>.amount {
						flex: 1;
						text-align: center;
						align-self: center;
						color: hsla(0, 0%, 100%, 0.7);
						font-weight: normal;
						display: flex;
						flex-direction: column;
						font-size: 24rpx;

						>.money {
							font-size: 44rpx;
						}

						>.info {
							>.sinfo {
								padding: 0rpx 10rpx;
								display: inline-block;
								border-radius: 8rpx;
								color: #424242;
								background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
							}
						}
					}

					>.info {
						flex: 1;
						display: flex;
						font-size: 24rpx;
						color: hsla(0, 0%, 100%, 0.7);
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

								>.text {
									padding-left: 10rpx;
								}
							}
						}
					}

					&.confirm {
						>.icon {
							>.text {
								color: #ff5252;
							}
						}

						>.info {
							display: flex;
							flex-direction: column;

							>.sinfo {
								font-size: 24rpx;
								color: hsla(0, 0%, 100%, 0.7);
								padding-bottom: 10rpx;
							}

							>.btn {
								width: 250rpx;

								::v-deep .u-button--plain {
									background-color: transparent !important;
								}

								::v-deep .u-button {
									height: 50rpx !important;
									border-radius: 8rpx !important;
								}
							}
						}
					}

					&.overtime {
						>.icon {
							>.text {
								color: #fb8c00;
							}
						}
					}

					&.fail {
						>.icon {
							>.text {
								color: #fb8c00;
							}
						}
					}
				}
			}
		}

		.block2 {
			margin-top: 20rpx;
			margin-left: 20rpx;
			margin-right: 20rpx;

			::v-deep .z-paging-content {
				height: calc(100vh - 96rpx - 86rpx - 100rpx);
			}

			::v-deep .zp-page-top {
				position: absolute;
			}

			.search {
				>.name {
					font-size: 32rpx;
					color: #ffffff;
				}

				>.values {
					padding-top: 20rpx;

					>.tag {
						display: inline-block;
						border: 2rpx solid hsla(0, 0%, 100%, 0.12);
						color: #fff;
						border-radius: 32rpx;
						font-size: 28rpx;
						padding: 10rpx 20rpx;
						margin-right: 20rpx;
						margin-bottom: 20rpx;

						>.icon {
							display: none;
						}

						&.active {
							background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
							display: inline-flex;
							color: #424242;
							justify-content: center;
							align-items: center;

							>.icon {
								display: inline-block;
							}

							>span {
								padding-left: 10rpx;
							}
						}
					}
				}
			}

			.lists {
				margin-top: 30rpx;

				>.item {
					display: flex;
					background-color: #424242;
					color: #fff;
					border-radius: 8rpx;
					padding: 32rpx;
					margin-bottom: 20rpx;
					color: rgba(255, 255, 255, 0.7);

					>.left-info {
						flex: 1;
						display: flex;
						flex-direction: column;

						>.name {
							font-size: 28rpx;
							padding-bottom: 20rpx;
						}

						>.amount {
							font-size: 48rpx;
						}
					}

					>.right-info {
						flex: 1;
						display: flex;
						font-size: 24rpx;
						justify-content: flex-end;
						align-items: center;

						>.sinfo {
							flex: 1;
							flex-direction: column;
							justify-content: flex-end;
							align-items: flex-end;
							text-align: right;

							>.row {
								padding-bottom: 10rpx;
							}
						}
					}
				}
			}
		}
	}
</style>
