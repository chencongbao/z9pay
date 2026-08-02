<template>
	<view class="content">
		<bob-header />
		<bob-tab :menus="menus" @tab="clickTab" />
		<view class="block1" v-show="tab == 1">
			<u-transition :show="step == 1" mode="slide-left">
				<view class="step1" v-show="step == 1">
					<image src="../../static/img/logo.png" mode="" class="img"></image>
					<u-button text="点击申购" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" v-on:click="searchOrder"></u-button>
				</view>
			</u-transition>
			<u-transition :show="step == 2" mode="slide-right">
				<view class="step2" v-if="step == 2">
					<view class="header">
						<view class="left" v-on:click="step = 1">
							<image src="../../static/img/img3.svg" mode="" class="lefticon"></image>
						</view>
						<view class="name">
							正在匹配申购
						</view>
						<view class="right">
							<image src="../../static/img/img2.svg" mode="" class="righticon"></image>
						</view>
					</view>
					<view class="lists" v-if="searchLists.length > 0">
						<view class="item" v-for="(vo,index) in searchLists" v-bind:key="index">
							<view class="label">
								<view class="amount">
									{{vo.amount}}
								</view>
								<view class="ordernumber">
									单号{{vo.ordernumber_format}}
								</view>
							</view>
							<view class="btn" v-on:click="receviceOrder(vo.id)">
								<u-button type="primary" text="抢单购币"></u-button>
							</view>
						</view>
						<view class="logo">
							<image src="../../static/img/img1.svg" mode="" class="logo"></image>
						</view>
					</view>
					<view class="middle" v-else>
						<image src="../../static/img/img1.svg" mode="" class="logo"></image>
						<view class="tip">
							请耐心等待...
						</view>
					</view>
				</view>
			</u-transition>
			<u-transition :show="step == 3" mode="slide-right">
				<view class="step3" v-if="!$isempty(orderDetail)">
					<view class="title">
						申购转账
					</view>
					<view class="tip">
						<view class="icon">
							<u-icon name="info-circle-fill" color="#EE8604" size="40"></u-icon>
						</view>
						<view class="text">
							<view class="text1">
								请准确按照汇款金额 {{orderDetail.amount}} 转账 过期时间：{{orderDetail.pay_overtime}}
							</view>
						</view>
					</view>
					<view class="ordernumber">
						<view class="name">
							订单号
						</view>
						<view class="text">
							{{orderDetail.ordernumber}}
						</view>
					</view>
					<view class="item">
						<view class="name">
							收款银行
						</view>
						<view class="text">
							{{orderDetail.bank_name}}
						</view>
						<u-button text="复制" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="copy" v-on:click="$copy(orderDetail.bank_name)"></u-button>
					</view>
					<view class="item">
						<view class="name">
							收款账户
						</view>
						<view class="text">
							{{orderDetail.card_no}}
						</view>
						<u-button text="复制" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="copy" v-on:click="$copy(orderDetail.card_no)"></u-button>
					</view>
					<view class="item">
						<view class="name">
							账户姓名
						</view>
						<view class="text">
							{{orderDetail.holder_name}}
						</view>
						<u-button text="复制" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="copy" v-on:click="$copy(orderDetail.holder_name)"></u-button>
					</view>
					<view class="item">
						<view class="name">
							汇款金额
						</view>
						<view class="text">
							{{orderDetail.amount}}
						</view>
						<u-button text="复制" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="copy" v-on:click="$copy(orderDetail.amount)"></u-button>
					</view>
					<u-button text="一键复制所有信息" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="onekey" v-on:click="copyAll"></u-button>
					<view class="btns">
						<u-button text="撤销转账" :plain="true" :hairline="true" class="btn" type="primary" iconColor="#424242" color="#424242" v-on:click="cancelOrder"></u-button>
						<u-button text="我已转账" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="btn" v-on:click="step=4"></u-button>
					</view>
				</view>
			</u-transition>
			<u-transition :show="step == 4" mode="slide-left">
				<view class="step3 step4" v-if="!$isempty(orderDetail)">
					<view class="title">
						上传汇款回单
					</view>
					<view class="item">
						<view class="name">
							订单号
						</view>
						<view class="text">
							{{orderDetail.ordernumber}}
						</view>
					</view>
					<view class="item">
						<view class="name">
							收款银行
						</view>
						<view class="text">
							{{orderDetail.bank_name}}
						</view>
					</view>
					<view class="item">
						<view class="name">
							收款账号
						</view>
						<view class="text">
							{{orderDetail.card_no}}
						</view>
					</view>
					<view class="item">
						<view class="name">
							收款姓名
						</view>
						<view class="text">
							{{orderDetail.holder_name}}
						</view>
					</view>
					<view class="item">
						<view class="name">
							汇款金额
						</view>
						<view class="text">
							{{orderDetail.amount}}
						</view>
					</view>
					<u-upload :maxCount="1" @afterRead="afterRead1" accept="image">
						<view class="block">
							<view class="icon">
								<u-icon name="attach" color="#ffffff" size="60"></u-icon>
							</view>
							<view class="item">
								<view class="name" v-if="form.pay_certificate_1">
									点击选择带公章回执单 (必选)
								</view>
								<view class="text" v-if="!form.pay_certificate_1">
									点击选择带公章回执单 (必选)
								</view>
								<view class="info" v-if="form.pay_certificate_1">
									<view class="name">
										{{getFileName(form.pay_certificate_1)}}
									</view>
									<view class="del" v-on:click.stop="deletefile(1)">
										<u-icon name="close" color="#ffffff" size="40"></u-icon>
									</view>
								</view>
							</view>
						</view>
					</u-upload>
					<u-upload :maxCount="1" @afterRead="afterRead2" accept="image">
						<view class="block">
							<view class="icon">
								<u-icon name="attach" color="#ffffff" size="60"></u-icon>
							</view>
							<view class="item">
								<div class="name" v-if="form.pay_certificate_2">点击选择带完整卡号回执单 (可选)</div>
								<div class="text" v-if="!form.pay_certificate_2">点击选择带完整卡号回执单 (可选)</div>
								<view class="info" v-if="form.pay_certificate_2">
									<view class="name">
										{{getFileName(form.pay_certificate_2)}}
									</view>
									<view class="del" v-on:click.stop="deletefile(2)">
										<u-icon name="close" color="#ffffff" size="40"></u-icon>
									</view>
								</view>
							</view>
						</view>
					</u-upload>
					<u-upload :maxCount="1" @afterRead="afterRead3" accept="image">
						<view class="block">
							<view class="icon">
								<u-icon name="attach" color="#ffffff" size="60"></u-icon>
							</view>
							<view class="item">
								<div class="name" v-if="form.pay_certificate_3">点击选择银行流水明细 (可选)</div>
								<div class="text" v-if="!form.pay_certificate_3">点击选择银行流水明细 (可选)</div>
								<view class="info" v-if="form.pay_certificate_3">
									<view class="name">
										{{getFileName(form.pay_certificate_3)}}
									</view>
									<view class="del" v-on:click.stop="deletefile(3)">
										<u-icon name="close" color="#ffffff" size="40"></u-icon>
									</view>
								</view>
							</view>
						</view>
					</u-upload>
					<u-button text="提交回执" iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" class="onekey" v-on:click="submitOrder" throttleTime="1000"></u-button>
				</view>
			</u-transition>
		</view>
		<view class="block2" v-show="tab == 2">
			<z-paging ref="paging" v-model="lists" @query="fetchData" :fixed="false" :use-page-scroll="false">
				<bob-search slot="top" v-on:search="search" v-on:reset="reset" v-on:submit="submit" v-model="form1.ordernumber">
					<view class="search">
						<view class="name">
							时间段
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in time1List" v-bind:key="index2" v-bind:class="{'active':vo2.id == form1.time}" v-on:click="selectTime1(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
						<view class="name">
							订单状态
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in status1List" v-bind:key="index2" v-bind:class="{'active':vo2.id == form1.status}" v-on:click="selectStatus(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
					</view>
				</bob-search>
				<view class="lists">
					<view class="item" v-for="(vo1,index1) in lists" v-bind:key="index1" v-on:click="detail(vo1)">
						<view class="icon" v-bind:class="{ confirm: vo1.status == 2,fail:vo1.status == 5 }">
							<IconSvg name="succeeded" color="#4caf50" width="48" height="48" />
							<view class="text">
								{{vo1.status_text}}
							</view>
						</view>
						<view class="amount" v-if="vo1.status == 4">
							{{vo1.actual_amount}}
						</view>
						<view class="info">
							<view class="sinfo">
								<view class="amount">
									<span>订单金额</span>
									<span class="text">{{vo1.amount}}</span>
								</view>
								<view class="date">
									{{vo1.create_time}}
								</view>
								<view class="date">
									{{vo1.ordernumber}}
								</view>
							</view>
							<uni-icons type="right" color="#ffffff" size="20"></uni-icons>
						</view>
					</view>
				</view>
			</z-paging>
		</view>
		<view class="block3" v-show="tab == 3">
			<z-paging ref="paging1" v-model="lists1" @query="fetchData1" :fixed="false" :use-page-scroll="false">
				<bob-search slot="top" v-on:search="search2" v-on:reset="reset2" v-on:submit="submit2" v-model="form2.ordernumber">
					<view class="search">
						<view class="name">
							时间段
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in time1List" v-bind:key="index2" v-bind:class="{'active':vo2.id == form2.time}" v-on:click="selectTime2(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
					</view>
				</bob-search>
				<view class="lists">
					<view class="item" v-for="(vo, index) in lists1" v-bind:key="index">
						<view class="left-info">
							<view class="name">代付金额</view>
							<view class="amount">
								{{ vo.actual_amount }}
							</view>
						</view>
						<view class="right-info">
							<view class="sinfo">
								<view class="row amount">
									<span>收益:</span>
									<span>{{ vo.user_commission }}</span>
								</view>
								<view class="row date">
									{{ vo.create_time }}
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
		<bob-detail ref="order_detail" :rows="rows" />
		<u-notify ref="uNotify" class="tip" message="您有新的代付订单，请手动点击申购订单"></u-notify>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				menus: [{
					id: 1,
					name: "申请代付",
					active: 1
				}, {
					id: 2,
					name: "申请记录",
					active: 0
				}, {
					id: 3,
					name: "交易流水",
					active: 0
				}],
				tab: 1,
				step: 1,
				searchLists: [],
				orderDetail: "",
				status1List: [{
						id: 2,
						name: '待支付'
					},
					{
						id: 7,
						name: '待确认'
					},
					{
						id: 4,
						name: '成功'
					},
					{
						id: 5,
						name: '失败'
					}
				],
				rows: [],
				time1List: [{
						id: 1,
						name: '今天'
					},
					{
						id: 2,
						name: '所有'
					}
				],
				form: {
					pay_certificate_1: "",
					pay_certificate_2: "",
					pay_certificate_3: ""
				},
				form1: {
					time: 1,
					status: 0,
					ordernumber: ""
				},
				lists: [],
				lists1: [],
				form2: {
					time: 1,
					ordernumber: ''
				},
				transferOrderHandler: null,
				searchOrderTimer: null
			}
		},
		onLoad() {
			this.transferOrderHandler = (event) => {
				if (event.data.user_id == 0) {
					this.$refs.uNotify.show({
						top: 2,
						type: 'warning',
						message: '您有新的代付订单，请手动点击申购订单',
						duration: 1000 * 5,
						fontSize: 28
					});
				}
				if (event.data.user_id > 0) {
					if (this.tab == 1) {
						this.initOrder();
					}
				}
			};
			uni.$on('transferOrder', this.transferOrderHandler);
		},
		onUnload() {
			if (this.transferOrderHandler) uni.$off('transferOrder', this.transferOrderHandler);
			if (this.searchOrderTimer) clearTimeout(this.searchOrderTimer);
		},
		onShow() {
			this.initOrder();
		},
		methods: {
			clickTab(tab) {
				this.tab = tab;
			},
			detail() {
				this.$refs.popup.open();
			},
			initOrder() {
				this.$ajax.get("v2/transfer-orders/initOrder").then(res => {
					if (res && res.data) {
						this.step = 3;
						this.orderDetail = res.data.order;
					}
				}).catch(() => {});
			},
			searchOrder() {
				this.step = 2;
				if (this.searchOrderTimer) clearTimeout(this.searchOrderTimer);
				this.searchOrderTimer = setTimeout(() => {
					this.searchOrderTimer = null;
					this.$ajax.get("v2/transfer-orders/searchOrder").then(res => {
						if (res) {
							this.searchLists = res.data.lists.lists;
						}
					});
				}, 3000);
			},
			receviceOrder(id) {
				this.$ajax.get("v2/transfer-orders/receviceOrder", {
					id: id
				}).then(res => {
					if (res) {
						this.orderDetail = res.data.order;
						this.step = 3;
					}
				}).catch(() => {});
			},
			copyAll() {
				let data = this.orderDetail.bank_name + "\n" + this.orderDetail.card_no + "\n" + this.orderDetail
					.holder_name + "\n" + this.orderDetail.amount;
				this.$copy(data);
			},
			getFileName(path) {
				return path.substring(path.lastIndexOf('/') + 1);
			},
			afterRead1(event) {
				this.$ajax.uploadImage(event.file.url).then(res => {
					if (res) {
						this.form.pay_certificate_1 = res.data.path;
					}
				}).catch(() => {});
			},
			deletefile(index) {
				if (index == 1) {
					this.form.pay_certificate_1 = "";
				}
				if (index == 2) {
					this.form.pay_certificate_2 = "";
				}
				if (index == 3) {
					this.form.pay_certificate_3 = "";
				}
			},
			afterRead2(event) {
				this.$ajax.uploadImage(event.file.url).then(res => {
					if (res) {
						this.form.pay_certificate_2 = res.data.path;
					}
					}).catch(() => {});
				},
				afterRead3(event) {
				this.$ajax.uploadImage(event.file.url).then(res => {
					if (res) {
						this.form.pay_certificate_3 = res.data.path;
					}
					}).catch(() => {});
				},
				cancelOrder() {
				uni.showModal({
					title: "提示",
					content: "撤销申购?",
					cancelText: "取消",
					confirmText: "确定",
					success: res => {
						if (res.confirm) {
							this.$ajax.get("v2/transfer-orders/cancelOrder", {
								id: this.orderDetail.id
							}).then(res => {
								if (res) {
									this.step = 1;
								}
							});
						}
					}
				})
			},
			submitOrder() {
				this.$ajax.post("v2/transfer-orders/submitOrder", Object.assign({
					id: this.orderDetail.id
				}, this.form)).then(res => {
					if (res) {
						this.form.pay_certificate_1 = "";
						this.form.pay_certificate_2 = "";
						this.form.pay_certificate_3 = "";
						this.step = 1;
						this.tab = 2;
						this.menus.map(value => {
							value.active = 0;
							if (value.id == 2) {
								value.active = 1;
							}
						})
						this.$nextTick(() => {
							this.$refs.paging.reload();
						});
					}
				});
			},
			fetchData(pageNo, pageSize) {
				this.$ajax.get("v2/transfer-orders/index", Object.assign({
					page: pageNo
				}, this.form1)).then(res => {
					if (res) {
						this.$refs.paging.complete(res.data.lists.lists);
					}
				});
			},
			search() {
				this.$refs.paging.reload();
			},
			reset() {
				this.form1 = {
					time: 1,
					status: 0,
					ordernumber: ""
				};
				this.$refs.paging.reload();
			},
			submit() {
				this.$refs.paging.reload();
			},
			selectStatus(item) {
				this.form1.status = item.id;
			},
			selectTime1(item) {
				this.form1.time = item.id;
			},
			detail(item) {
				let data = [];
				data.push({
					name: "订单状态",
					value: item.status_text
				});
				data.push({
					name: "订单号",
					value: item.ordernumber,
					copy: true
				});
				data.push({
					name: "银行账号",
					value: item.pay_info
				});
				data.push({
					name: "订单金额",
					value: item.amount
				});
				data.push({
					name: "实际金额",
					value: item.actual_amount
				});
				data.push({
					name: "创建时间",
					value: item.create_time
				});
				this.rows = data;
				this.$refs.order_detail.open();
			},
			fetchData1(pageNo, pageSize) {
				this.$ajax
					.get(
						'v2/transfer-orders/logs',
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
	}
</script>

<style lang="less" scoped>
	.content {
		background-color: #1c1c1c !important;
		left: var(--window-left) !important;
		right: var(--window-right) !important;

		.block1 {
			margin: 20rpx;
			background-color: #424242;
			color: #fff;
			border-radius: 8rpx;

			.step1 {
				padding: 40rpx 24rpx;
				display: flex;
				flex-direction: column;
				justify-content: center;
				align-items: center;

				>.img {
					width: 376rpx;
					height: 376rpx;
					border-radius: 50%;
					margin-bottom: 50rpx;
				}

				::v-deep .u-button__text {
					color: #424242 !important;
					font-size: 28rpx !important;
					font-weight: 600;
				}
			}

			.step2 {
				padding: 20rpx 24rpx;

				>.header {
					display: flex;

					>.left {
						width: 50rpx;
						height: 100rpx;
						display: flex;
						justify-content: center;
						align-items: center;

						>.lefticon {
							width: 40rpx;
							height: 40rpx;
						}
					}

					>.name {
						text-align: center;
						flex: 1;
						height: 100rpx;
						line-height: 100rpx;
						vertical-align: middle;
						color: #edd185;
						font-size: 36rpx;
					}


					>.right {
						width: 100rpx;

						>.righticon {
							width: 100rpx;
							height: 100rpx;
						}
					}
				}



				>.middle {
					flex: 1;
					display: flex;
					flex-direction: column;
					align-items: center;

					>.name {
						height: 100rpx;
						line-height: 100rpx;
						vertical-align: middle;
						color: #edd185;
						font-size: 36rpx;
					}

					>.logo {
						margin-top: 100rpx;
						margin-bottom: 100rpx;
						width: 300rpx;
						height: 300rpx;
					}

					>.tip {
						padding-bottom: 32rpx;
						font-size: 24rpx;
						color: #edd185;
					}
				}

				>.lists {
					>.item {
						color: #edd185;
						padding: 20rpx;
						display: flex;
						justify-content: space-between;
						align-items: center;
						box-shadow: 0 6rpx 10rpx -2rpx rgba(0, 0, 0, .2), 0 10rpx 16rpx 0 rgba(0, 0, 0, .14), 0 2rpx 28rpx 0 rgba(0, 0, 0, .12) !important;

						>.label {
							flex: 1;

							>.amount {
								font-size: 40rpx;
							}

							>.ordernumber {
								font-size: 24rpx;
							}
						}

						>.btn {
							align-self: center;
							min-width: 100rpx;
							height: 56rpx;

							::v-deep .u-button--primary {
								background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
								border-color: #edd185;
							}

							::v-deep .u-button__text {
								color: #424242;
							}

							::v-deep .u-button {
								height: 56rpx;
							}
						}
					}

					>.logo {
						padding-top: 50rpx;
						padding-bottom: 100rpx;
						display: flex;
						justify-content: center;
						align-items: center;

						>.logo {
							width: 100px;
							height: 100rpx;
						}
					}


				}



			}

			.step3 {
				display: flex;
				flex-direction: column;
				padding: 30rpx 20rpx;

				>.title {
					text-align: center;
					font-size: 34rpx;
					color: #edd185;
					padding-bottom: 20rpx;
				}

				>.tip {
					border: 2rpx solid #EE8604;
					padding: 20rpx;
					display: flex;
					border-radius: 8rpx;
					margin-bottom: 40rpx;
					justify-content: space-between;
					align-items: center;

					>.icon {
						width: 50rpx;
					}

					>.text {
						flex: 1;

						>.text1 {
							color: #EE8604;
							font-size: 30rpx;
						}
					}
				}

				>.ordernumber {
					border: 4rpx solid #EDD185;
					position: relative;
					display: flex;
					padding: 20rpx 10rpx;
					border-radius: 8rpx;
					margin-bottom: 40rpx;

					>.name {
						position: absolute;
						left: 20rpx;
						top: -20rpx;
						background-color: #424242;
						color: #EDD185;
						font-size: 24rpx;
					}

					>.text {
						font-size: 24rpx;
					}
				}

				>.item {
					border: 2rpx solid #727272;
					position: relative;
					display: flex;
					padding: 20rpx 10rpx;
					border-radius: 8rpx;
					margin-bottom: 40rpx;
					justify-content: space-between;
					align-items: center;

					>.name {
						position: absolute;
						left: 20rpx;
						top: -20rpx;
						background-color: #424242;
						color: rgba(255, 255, 255, 0.7);
						font-size: 24rpx;
					}

					>.text {
						flex: 1;
						font-size: 28rpx;
					}

					>.copy {
						height: 50rpx;
						width: 100rpx;
						color: #424242 !important;
					}
				}

				>.onekey {
					margin-bottom: 30rpx;
					color: #424242 !important;
				}

				>.btns {
					display: flex;

					>.btn {
						&:first-child {
							color: #EDD185 !important;
							border-color: #EDD185 !important;
							background-color: #424242 !important;
						}

						&:last-child {
							margin-left: 32rpx;
							color: #424242 !important;
						}
					}
				}

				&.step4 {
					.block {
						display: flex;
						justify-content: space-between;
						align-items: center;
						width: 670rpx;

						>.icon {
							transform: rotate(-45deg);
							padding-bottom: 50rpx;
						}

						>.item {
							flex: 1;
							border: 2rpx solid #727272;
							position: relative;
							display: flex;
							padding: 25rpx 10rpx;
							border-radius: 8rpx;
							margin-bottom: 40rpx;
							justify-content: space-between;
							align-items: center;
							color: hsla(0, 0%, 100%, .7);
							font-size: 24rpx;

							>.name {
								position: absolute;
								left: 20rpx;
								top: -20rpx;
								background-color: #424242;
								color: rgba(255, 255, 255, 0.7);
								font-size: 24rpx;
							}

							>.text {
								flex: 1;
								font-size: 24rpx;
							}

							>.info {
								display: flex;
								overflow: hidden;

								>.name {
									flex: 1;
									overflow: hidden;
									white-space: nowrap;
									text-overflow: ellipsis;
									padding-left: 10rpx;
									width: 500rpx;
								}

								>.del {
									text-align: center;
									width: 40rpx;
									padding-left: 10rpx;
								}
							}
						}
					}
				}
			}
		}

		.block2 {
			margin: 20rpx;

			::v-deep .z-paging-content {
				height: calc(100vh - 96rpx - 86rpx - 110rpx);
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
						border: 2rpx solid hsla(0, 0%, 100%, .12);
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

						&.fail {
							>.icon {
								--color: #fb8c00 !important;
							}

							>.text {
								color: #fb8c00;
							}
						}

						&.confirm {
							>.icon {
								--color: #ff5252 !important;
							}

							>.text {
								color: #ff5252;
							}
						}
					}

					>.amount {
						flex: 1;
						text-align: center;
						align-self: center;
						color: hsla(0, 0%, 100%, .7);
						font-size: 48rpx;
						font-weight: normal;
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

								>.text {
									padding-left: 10rpx;
								}
							}
						}
					}
				}
			}
		}

		.block3 {
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
