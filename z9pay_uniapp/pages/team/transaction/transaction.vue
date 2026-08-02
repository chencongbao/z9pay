<template>
	<view class="content">
		<bob-header />
		<bob-tab :menus="menus" v-on:tab="clickTab" />
		<view class="block1" v-show="tab  == 1">
			<z-paging ref="paging1" v-model="lists1" @query="fetchData1" :fixed="false" :use-page-scroll="false">
				<BobSearch slot="top" v-model="form1.ordernumber" v-on:search="search1" v-on:reset="reset1" v-on:submit="submit1">
					<view class="search">
						<view class="name">
							订单状态
						</view>
						<view class="values">
							<view class="tag" v-for="(vo1,index1) in status1List" v-bind:key="index1" v-bind:class="{'active':vo1.id == form1.status}" v-on:click="selectStatus1(vo1)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo1.name}}</span>
							</view>
						</view>
						<view class="name">
							时间段
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in timeList" v-bind:key="index2" v-bind:class="{'active':vo2.id == form1.time}" v-on:click="selectTime1(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
					</view>
				</BobSearch>
				<view class="lists">
					<view class="item" v-for="(vo,index) in lists1" v-bind:key="index" v-bind:class="{ overtime: vo.status == 4, fail: vo.status == 6, confirm: vo.status == 3 || vo.status == 7 }" v-on:click="detail1(vo)">
						<view class="icon">
							<IconSvg name="succeeded" :color="vo.color" width="48" height="48" />
							<view class="text">
								{{vo.status_text}}
							</view>
						</view>
						<view class="amount">
							<view class="money">
								{{vo.amount}}
							</view>
							<view class="info">
								<view class="sinfo" v-on:click.stop="$copy(vo.pay_name)">
									付款人：{{vo.pay_name}}<uni-icons type="icon-fuzhi" custom-prefix="iconfont" color="#424242" class="icon"></uni-icons>
								</view>
							</view>
						</view>
						<view class="info" v-if="vo.status == 3 || vo.status == 7">
							<view class="sinfo">
								<view class="amount" style="text-align: right;">
									{{vo.pay_info}}
								</view>
								<view class="amount">
									{{vo.create_time}}
								</view>
								<view class="amount" v-if="vo.user">
									{{ vo.user.bname }}
								</view>
							</view>
							<view class="btn" v-on:click.stop="confirmPayButton(vo,index)" v-if="$store.state.self_add_bank == 1">
								<u-button type="primary" :plain="true" text="确认到账"></u-button>
							</view>
						</view>
						<view class="info" v-else>
							<view class="sinfo">
								<view class="amount" style="text-align: right;">
									{{vo.pay_info}}
								</view>
								<view class="amount">
									{{vo.create_time}}
								</view>
								<view class="amount" v-if="vo.user">
									{{ vo.user.bname }}
								</view>
							</view>
							<uni-icons type="right" color="#ffffff" size="20"></uni-icons>
						</view>
					</view>
				</view>
			</z-paging>
		</view>
		<view class="block2" v-if="tab  == 2">
			<z-paging ref="paging2" v-model="lists2" @query="fetchData2" :fixed="false" :use-page-scroll="false">
				<BobSearch slot="top" v-model="form2.ordernumber" v-on:search="search2" v-on:reset="reset2" v-on:submit="submit2">
					<view class="search">
						<view class="name">
							订单状态
						</view>
						<view class="values">
							<view class="tag" v-for="(vo1,index1) in status2List" v-bind:key="index1" v-bind:class="{'active':vo1.id == form2.status}" v-on:click="selectStatus2(vo1)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo1.name}}</span>
							</view>
						</view>
						<view class="name">
							时间段
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in timeList" v-bind:key="index2" v-bind:class="{'active':vo2.id == form2.time}" v-on:click="selectTime2(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
					</view>
				</BobSearch>
				<view class="lists">
					<view class="item" v-for="(vo1,index1) in lists2" v-bind:key="index1" v-on:click="detail2(vo1)" v-bind:class="{'overtime':vo1.status == 4,'confirm':vo1.status ==3 || vo1.status == 7}">
						<view class="icon">
							<IconSvg name="succeeded" :color="vo1.color" width="48" height="48" />
							<view class="text">
								{{vo1.status_text}}
							</view>
						</view>
						<view class="amount">
							{{vo1.amount}}
						</view>
						<view class="info">
							<view class="sinfo">
								<view class="amount">
									{{vo1.user ? vo1.user.bname : '未知金主'}}
								</view>
								<view class="date">
									{{vo1.pay_info}}
								</view>
								<view class="date">
									{{vo1.create_time}}
								</view>
							</view>
							<uni-icons type="right" color="#ffffff" size="20"></uni-icons>
						</view>
					</view>
				</view>
			</z-paging>
		</view>
		<view class="block3" v-if="tab  == 3">
			<z-paging ref="paging3" v-model="lists3" @query="fetchData3" :fixed="false" :use-page-scroll="false">
				<BobSearch slot="top" v-model="form3.ordernumber" placeholder="交易单号" v-on:search="search3" v-on:reset="reset3" v-on:submit="submit3">
					<view class="search">
						<view class="name">
							交易类型
						</view>
						<view class="values">
							<view class="tag" v-for="(vo1,index1) in typeList" v-bind:key="index1" v-bind:class="{'active':vo1.id == form3.type}" v-on:click="selectType(vo1)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo1.name}}</span>
							</view>
						</view>
						<view class="name">
							成交时间段
						</view>
						<view class="values">
							<view class="tag" v-for="(vo2,index2) in timeList" v-bind:key="index2" v-bind:class="{'active':vo2.id == form3.time}" v-on:click="selectTime3(vo2)">
								<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
								<span>{{vo2.name}}</span>
							</view>
						</view>
					</view>
				</BobSearch>
				<view class="lists">
					<view class="item" v-for="(vo1,index1) in lists3" v-bind:key="index1">
						<view class="left-info">
							<view class="name">
								{{vo1.new_order_type}}：{{vo1.amount > 0 ? "+"+vo1.amount : vo1.amount}}
							</view>
							<view class="name" v-if="vo1.order">
								订单金额：{{vo1.order.actual_amount}}
							</view>
						</view>
						<view class="info">
							<view class="sinfo">
								<view class="amount">
									{{vo1.user_bname}}
								</view>
								<view class="date" v-if="vo1.order">
									{{vo1.order.ordernumber}}
								</view>
								<view class="date">
									{{vo1.create_time}}
								</view>
							</view>
						</view>
					</view>
				</view>
			</z-paging>
		</view>
		<BobDetail :rows="rows" ref="detail" />
		<BobDetail title="核实到账信息" ref="confirm_order" class="confirm-order" v-if="!$isempty(row)">
			<view class="item">
				<view class="label">
					订单号
				</view>
				<view class="value">
					{{row.ordernumber}}
				</view>
			</view>
			<view class="item">
				<view class="label">
					账号信息
				</view>
				<view class="value">
					{{row.pay_info}}
				</view>
			</view>
			<view class="item">
				<view class="label">
					订单金额
				</view>
				<view class="value">
					{{row.amount}}
				</view>
			</view>
			<view class="item">
				<view class="label">
					创建时间
				</view>
				<view class="value">
					{{row.time}}
				</view>
			</view>
			<view class="amount">
				<view class="name">
					请输入实际入账金额
				</view>
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
	</view>
</template>

<script>
	export default {
		data() {
			return {
				menus: [{
					id: 1,
					name: "团队代收",
					active: 1
				}, {
					id: 2,
					name: "团队代付",
					active: 0
				}, {
					id: 3,
					name: "交易流水",
					active: 0
				}],
				tab: 1,
				lists1: [],
				form: {
					index: 0,
					order_id: 0,
					amount: ""
				},
				form1: {
					status: 0,
					time: 1,
					ordernumber: ""
				},
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
				timeList: [{
						id: 1,
						name: "今天"
					},
					{
						id: 2,
						name: "所有"
					}
				],
				rows: [],
				row: {},
				lists2: [],
				form2: {
					status: 0,
					time: 1,
					ordernumber: ""
				},
				status2List: [{
					id: 2,
					name: "待支付",
					active: 0
				}, {
					id: 4,
					name: "成功",
					active: 0
				}, {
					id: 5,
					name: "失败",
					active: 0
				}, {
					id: 7,
					name: "待确认",
					active: 0
				}],
				lists3: [],
				form3: {
					type: 0,
					time: 1,
					ordernumber: ""
				},
				typeList: [{
					id: 1,
					name: "代收",
					active: 0
				}, {
					id: 2,
					name: "代付",
					active: 0
				}]
			}
		},
		methods: {
			clickTab(tab) {
				this.tab = tab;
				if (this.tab == 1) {
					this.$nextTick(() => {
						this.$refs.paging1.reload();
					})
				}
				if (this.tab == 2) {
					this.$nextTick(() => {
						this.$refs.paging2.reload();
					})
				}
				if (this.tab == 3) {
					this.$nextTick(() => {
						this.$refs.paging3.reload();
					})
				}
			},
			fetchData1(pageNo, pageSize) {
				this.$ajax.get("v2/users/teamDepositOrderIndex", Object.assign({
					page: pageNo
				}, this.form1)).then(res => {
					if (res) {
						this.$refs.paging1.complete(res.data.lists.lists);
					}
				});
			},
			confirmPayButton(vo, index) {
				this.row = vo;
				this.form.amount = vo.amount;
				this.form.order_id = vo.id;
				this.form.index = index;
				this.$nextTick(() => {
					this.$refs.confirm_order.open();
				})
			},
			submitPay() {
				this.$refs.confirm_order.close();
				this.$ajax.post("v2/agent-users/confirm-pay", this.form).then(res => {
					if (res) {
						this.$set(this.lists1[this.form.index], 'status', 5);
						this.$set(this.lists1[this.form.index], 'status_text', "成功");
						this.$set(this.lists1[this.form.index], 'color', "#4caf50");
					}
				});
			},
			canncelPay() {
				this.$refs.confirm_order.close();
			},
			selectStatus1(item) {
				this.form1.status = item.id;
			},
			selectTime1(item) {
				this.form1.time = item.id;
			},
			search1() {
				this.$refs.paging1.reload();
			},
			reset1() {
				this.form1 = {
					status: -1,
					time: 2,
					name: "",
					amount: "",
					ordernumber: ""
				};
				this.$refs.paging1.reload();
			},
			submit1() {
				this.$refs.paging1.reload();
			},
			detail1(vo) {
				let data = [];
				if (vo.user) {
					data.push({
						name: "金主",
						value: vo.user.bname
					});
				}

				if (vo.pay_status == 2) {
					data.push({
						name: "付款状态",
						value: "付方已确认"
					});
					if (vo.pay_certificate) {
						data.push({
							name: "付款凭证",
							value: vo.pay_certificate,
							image: true
						});
					}
				}
				if (vo.pay_status == 3) {
					data.push({
						name: "付款状态",
						value: "付方已取消"
					});
				}
				data.push({
					name: "订单状态",
					value: vo.status_text
				});
				data.push({
					name: "订单号",
					value: vo.ordernumber,
					copy: true
				});
				data.push({
					name: "银行账号",
					value: vo.pay_info,
					copy: true
				});
				data.push({
					name: "订单金额",
					value: vo.amount
				});
				data.push({
					name: "实际金额",
					value: vo.actual_amount
				});
				if (vo.status == 5) {
					data.push({
						name: "收益",
						value: vo.user_commission
					});
				}
				data.push({
					name: "创建时间",
					value: vo.create_time
				});
				if (vo.status == 5) {
					data.push({
						name: "成交时间",
						value: vo.time
					});
				}
				this.rows = data;
				this.$refs.detail.open();
			},
			fetchData2(pageNo, pageSize) {
				this.$ajax.get("v2/users/teamTransferOrderIndex", Object.assign({
					page: pageNo
				}, this.form2)).then(res => {
					if (res) {
						this.$refs.paging2.complete(res.data.lists.lists);
					}
				});
			},
			selectStatus2(item) {
				this.form2.status = item.id;
			},
			selectTime2(item) {
				this.form2.time = item.id;
			},
			search2() {
				this.$refs.paging2.reload();
			},
			reset2() {
				this.form2 = {
					status: -1,
					time: 2,
					name: "",
					amount: "",
					ordernumber: ""
				};
				this.$refs.paging2.reload();
			},
			submit2() {
				this.$refs.paging2.reload();
			},
			detail2(vo) {
				let data = [];
				data.push({
					name: "订单状态",
					value: vo.status_text
				});
				data.push({
					name: "订单号",
					value: vo.ordernumber,
					copy: true
				});
				data.push({
					name: "银行账号",
					value: vo.pay_info
				});
				data.push({
					name: "订单金额",
					value: vo.amount
				});
				data.push({
					name: "实际金额",
					value: vo.actual_amount
				});
				if (vo.status == 5) {
					data.push({
						name: "收益",
						value: vo.user_commission
					});
				}
				data.push({
					name: "创建时间",
					value: vo.create_time
				});
				if (vo.status == 5) {
					data.push({
						name: "成交时间",
						value: vo.time
					});
				}
				this.rows = data;
				this.$refs.detail.open();
			},
			fetchData3(pageNo, pageSize) {
				this.$ajax.get("v2/users/teamBalanceLogIndex", Object.assign({
					page: pageNo
				}, this.form3)).then(res => {
					if (res) {
						this.$refs.paging3.complete(res.data.lists.lists);
					}
				});
			},
			selectType(vo) {
				this.form3.type = vo.id;
			},
			selectTime3(item) {
				this.form3.time = item.id;
			},
			search3() {
				this.$refs.paging3.reload();
			},
			reset3() {
				this.form3 = {
					type: 0,
					time: 1,
					ordernumber: ""
				};
				this.$refs.paging3.reload();
			},
			submit3() {
				this.$refs.paging3.reload();
			},
		}
	}
</script>

<style lang="less" scoped>
	.content {
		min-height: 100vh;
		background-color: #1c1c1c !important;

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
					}

					>.amount {
						flex: 1;
						text-align: center;
						align-self: center;
						color: hsla(0, 0%, 100%, .7);
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
								color: hsla(0, 0%, 100%, .7);
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
					}

					>.amount {
						flex: 1;
						text-align: left;
						align-self: center;
						color: hsla(0, 0%, 100%, .7);
						font-size: 48rpx;
						font-weight: normal;
					}

					>.info {
						flex: 2;
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
								color: hsla(0, 0%, 100%, .7);
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
					padding: 32rpx;
					margin-bottom: 20rpx;

					>.left-info {
						flex: 1;
						display: flex;
						flex-direction: column;
						color: hsla(0, 0%, 100%, .7);
						align-self: center;

						>.name {
							font-size: 28rpx;
						}

						>.amount {
							font-size: 40rpx;
						}

						>.money {
							font-size: 28rpx;
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
	}
</style>
