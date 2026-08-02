<template>
	<view class="content">
		<z-paging ref="paging" v-model="lists" @query="fetchData">
			<view slot="top">
				<uni-nav-bar left-icon="arrow-left" title="收款卡管理" backgroundColor="#121212" :border="false" color="#edd185" height="96rpx" class="header" @clickLeft="$back()" left-width="40rpx">
					<view class="header_right" slot="right" v-on:click="$refs.addMenu.open()">
						<uni-icons type="plusempty" color="#272727" size="12" class="icon"></uni-icons>
					</view>
				</uni-nav-bar>
				<view class="searchs">
					<BobSearch placeholder="姓名/卡号" v-on:search="search" v-on:reset="reset" v-on:submit="submit" v-model="form.keyword">
						<view class="search">
							<view class="name"> 收单状态 </view>
							<view class="values">
								<view class="tag" v-for="(vo1, index1) in status1List" v-bind:key="index1" v-bind:class="{ active: vo1.id == form.collection_status }" v-on:click="selectStatus(vo1)">
									<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
									<span>{{ vo1.name }}</span>
								</view>
							</view>
							<view class="name"> 收款方式 </view>
							<view class="values">
								<view class="tag" v-for="(vo1, index1) in accountTypeList" v-bind:key="index1" v-bind:class="{ active: vo1.id == form.account_type }" v-on:click="selectPayment(vo1)">
									<uni-icons type="checkmarkempty" class="icon" color="#424242" size="20"></uni-icons>
									<span>{{ vo1.name }}</span>
								</view>
							</view>
						</view>
					</BobSearch>
					<view class="del" v-if="lists.length > 0 && $store.state.action_delete == 1">
						<view class="text" v-on:click="delAll"> 批量删除 </view>
					</view>
				</view>
			</view>

			<view class="main">
				<view class="lists">
					<view class="item" v-bind:class="{ active: vo.show }" v-for="(vo, index) in lists" v-bind:key="index">
						<view class="top-info">
							<view class="top-header" v-on:click="open(index)">
								<view class="label">
									<uni-icons type="person-filled" color="#121212" size="50rpx" class="icon1"></uni-icons>
									<view class="name">
										{{ vo.user_name }}
									</view>
								</view>
								<view class="top-right-info">
									<image src="../../../static/img/arraw.svg" mode="" class="img"></image>
								</view>
							</view>
							<view class="top-bottom-info">
								<view class="label">
									<view class="name">
										{{ vo.name }}
									</view>
								</view>
								<view class="label" v-if="vo.account_type == 1 || vo.account_type == 2 || vo.account_type == 4 || vo.account_type == 6">
									<span class="sname">收款卡：</span>
									<view class="sname">
										{{ vo.card_no }}
									</view>
								</view>
								<view class="label" v-if="vo.payment_qrcode" v-on:click.stop="$preview(vo.payment_qrcode_format)">
									<span class="sname">点击查看二维码：</span>
									<view class="sname">
										<uni-icons custom-prefix="iconfont" type="icon-erweima" size="20" class="icon" color="#ffffff"></uni-icons>
									</view>
								</view>
								<view class="label" v-if="vo.account_type == 1">
									<span class="sname">收款银行：</span>
									<span class="sname">{{ vo.bank_code ? vo.bank_code.name : '未知银行' }}</span>
								</view>
								<view class="label" v-else>
									<span class="sname">账号类型：</span>
									<span class="sname">{{ vo.payment_name }}</span>
								</view>
								<view class="label">
									<span class="sname">收款状态：</span>
									<view class="top-right-main">
										<view class="status" v-bind:class="{'forbid':vo.collection_status == 0}">
											{{ vo.collection_status_text }}
										</view>
									</view>
								</view>
							</view>
						</view>

						<view class="middle-info">
							<view class="rows">
								<view class="item">
									<view class="name"> 今日跑量 </view>
									<view class="value">
										{{ vo.total_amount }}
									</view>
								</view>
								<view class="item">
									<view class="name"> 今日交易数 </view>
									<view class="value">
										{{ vo.total_number }}
									</view>
								</view>
								<view class="item">
									<view class="name"> 今日收益 </view>
									<view class="value">
										{{ vo.total_income }}
									</view>
								</view>
							</view>
						</view>
						<view class="bottom-info">
							<view class="rows">
								<view class="item">
									<view class="name"> 参考余额 </view>
									<view class="value">
										{{ vo.balance_amount }}
									</view>
								</view>
								<view class="item">
									<view class="name"> 单笔限额 </view>
									<view class="value"> {{ vo.limint_min_amount }}-{{ vo.limint_max_amount }} </view>
								</view>
								<view class="item">
									<view class="name"> 全天限额 </view>
									<view class="value">
										{{ vo.limint_day_amount }}
									</view>
								</view>
							</view>
							<view class="rows line">
								<view class="action">
									<view class="bt1">
										<view class="status" v-if="vo.is_switch == 1">
											<switch style="transform: scale(0.8)" color="rgba(237, 209, 133,.12)" :checked="vo.collection_status == 1" @change="setStatus(vo, index)" />
										</view>
										<view class="del" v-if="$store.state.action_delete == 1" v-on:click="delBank(vo.id, index)">
											<uni-icons type="trash-filled" color="#edd185" size="60rpx"></uni-icons>
										</view>
									</view>
									<view class="bt2">
										<span class="edit" v-on:click="edit(vo, index)">修改信息</span>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</z-paging>

		<uni-popup ref="addMenu" type="top" maskBackgroundColor="transparent" :animation="false">
			<view class="addMenuContent">
				<view class="item" v-on:click="openAddForm(vo2.id)" v-for="(vo2, index) in accountTypeList" v-bind:key="index">
					新增{{ vo2.name }} </view>
			</view>
		</uni-popup>

		<view @touchmove.stop.prevent="moveHandle">
			<uni-popup ref="addFormContent" type="top">
				<view class="formContent">
					<view class="password">
						<view class="header">
							<view class="close" v-on:click="$refs.addFormContent.close()">
								<uni-icons type="closeempty" color="#1c1c1c" size="28"></uni-icons>
							</view>
							<view class="name" v-if="form1.id > 0"> 编辑{{ form1_account_type_name }} </view>
							<view class="name" v-else> 新增{{ form1_account_type_name }} </view>
						</view>
						<scroll-view scroll-y="true" class="scroll-view-forms">
							<view class="forms">
								<view class="item" v-if="form1.id == 0">
									<BobSelect label="所属金主" type="text" v-model="form1.user_id" :options="users" :value="form1.user_id" />
								</view>
								<view class="item">
									<BobInput label="收款人姓名" type="text" v-model="form1.name" />
									<view class="help"> 银行卡/支付宝/微信/数字人民币等持卡人姓名 </view>
								</view>
								<view class="item" v-if="form1.account_type == 1">
									<BobSelect label="收款银行" type="text" v-model="form1.bank_id" :options="banks" :value="form1.bank_id" />
								</view>
								<view class="item" v-if="form1.account_type == 1 || form1.account_type == 2 || form1.account_type == 4 || form1.account_type == 6">
									<BobInput label="收款账号" type="text" v-model="form1.card_no" />
									<view class="help"> 银行卡/支付宝/微信/数字人民币等账号 </view>
								</view>
								<view class="item">
									<BobSelect label="收款编码" type="text" v-model="form1.payment_id" :options="payments" :value="form1.payment_id" />
								</view>
								<template v-if="action_limit_card == 1">
									<view class="item">
										<BobInput label="单笔最低限额" type="text" v-model="form1.limint_min_amount" />
										<view class="help"> 不填则默认不限制，最多保留2位小数 </view>
									</view>
									<view class="item">
										<BobInput label="单笔最高限额" type="text" v-model="form1.limint_max_amount" />
										<view class="help"> 不填则默认不限制，最多保留2位小数 </view>
									</view>
									<view class="item">
										<BobInput label="全天限额" type="text" v-model="form1.limint_day_amount" />
										<view class="help"> 不填则默认不限制，最多保留2位小数 </view>
									</view>
									<view class="item">
										<BobInput label="全天限接单数量" type="text" v-model="form1.limit_day_order_number" />
										<view class="help"> 不填则默认不限制 </view>
									</view>
								</template>
								<view class="item" v-if="form1.account_type == 2 || form1.account_type == 3 || form1.account_type == 5   || form1.account_type == 28">
									<u-upload :maxCount="1" @afterRead="afterRead2" accept="image">
										<view class="block">
											<view class="icon">
												<u-icon name="attach" color="#ffffff" size="60"></u-icon>
											</view>
											<view class="item">
												<div class="name" v-if="form1.payment_qrcode">点击选择二维码图片</div>
												<div class="text" v-if="!form1.payment_qrcode">点击选择二维码图片</div>
												<view class="info" v-if="form1.payment_qrcode">
													<view class="name">
														{{ getFileName(form1.payment_qrcode) }}
													</view>
													<view class="del" v-on:click.stop="deletefile(2)">
														<u-icon name="close" color="#ffffff" size="40"></u-icon>
													</view>
												</view>
											</view>
										</view>
									</u-upload>
								</view>
								<u-button iconColor="#424242" color="linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185)" text="提交" class="btn" v-on:click="submitData"></u-button>
							</view>
						</scroll-view>
					</view>
				</view>
			</uni-popup>
		</view>
	</view>
</template>

<script>
	import { getImgQRCodeInfo } from "wechat-qrcode-ocr-wasm";
	export default {
		data() {
			return {
				action_limit_card: 0,
				windowHeight: 0,
				banks: [],
				lists: [],
				users: [],
				status1List: [{
						id: 0,
						name: "收单关闭",
					},
					{
						id: 1,
						name: "收单开启",
					},
				],
				form: {
					account_type: 0,
					collection_status: -1,
					keyword: "",
				},
				payments: [],
				accountTypeList: [],
				index: -1,
				form1: {
					id: 0,
					name: "",
					bank_id: 0,
					account_type: 0,
					user_id: 0,
					card_no: "",
					limint_min_amount: 0,
					limint_max_amount: 0,
					limint_day_amount: 0,
					payment_qrcode: "",
					limit_day_order_number: 0,
				},
			};
		},
		onLoad() {
			this.windowHeight = uni.getSystemInfoSync().windowHeight;
		},
		computed: {
			form1_account_type_name() {
				let name = "";
				if (this.accountTypeList.length > 0 && this.form1.account_type > 0) {
					this.accountTypeList.map((item, index) => {
						if (item.id == this.form1.account_type) {
							name = item.name;
						}
					});
				}
				return name;
			},
		},
		methods: {
			getCode(url) {
				return getImgQRCodeInfo({
					wasmBinaryFile: "/static/wasm/onlyWechatWasmFile.data", // http://localhost:8080/static/wasm/onlyWechatWasmFile.data
					wechatQRcodeFile: "/static/wasm/wechatQRcodeFile.data", // http://localhost:8080/static/wasm/wechatQRcodeFile.data
					url
					});
				},
				moveHandle() {},
			getFileName(path) {
				return path.substring(path.lastIndexOf("/") + 1);
			},
			afterRead2(event) {
				this.$ajax.uploadImage(event.file.url).then((res) => {
					if (res) {
						if (this.form1.account_type == 2 || this.form1.account_type == 3) {
							this.getCode(event.file.url).then(result => {
								if (result.data[0]) {
									this.form1.payment_qrcode_url = result.data[0];
								}
							})
						}
						this.form1.payment_qrcode = res.data.path;
					}
					}).catch(() => {});
				},
				delBank(id, index) {
				this.$confirm("确定删除", () => {
					this.$ajax.delete("v2/agent-users/bank-destroy/" + id).then((res) => {
						if (res) {
							this.lists.splice(index, 1);
						}
					});
				});
			},
			delAll() {
				this.$confirm("确定删除所有收款卡", () => {
					this.$ajax.get("v2/agent-users/clear-bank").then((res) => {
						if (res) {
							this.lists = [];
						}
					});
				});
			},
			fetchData(pageNo, pageSize) {
				this.$ajax.get("v2/agent-users/bank-index",
					Object.assign({
							page: pageNo,
						},
						this.form
					)
				).then((res) => {
					if (res) {
						this.action_limit_card = res.data.action_limit_card;
						this.payments = res.data.payments;
						this.banks = res.data.banks;
						this.accountTypeList = res.data.accountTypeList;
						this.users = res.data.users;
						this.$refs.paging.complete(res.data.lists.lists);
					}
				});
			},
			submitData() {
				if (this.form1.id > 0) {
					this.$ajax.put("v2/agent-users/bank-update/" + this.form1.id, this.form1).then((res) => {
						if (res) {
							this.form1 = {
								id: 0,
								name: "",
								user_id: 0,
								bank_id: 0,
								payment_id: 0,
								account_type: 0,
								card_no: "",
								limint_min_amount: 0,
								limint_max_amount: 0,
								limint_day_amount: 0,
								limit_day_order_number: 0,
								payment_qrcode: "",
								payment_qrcode_url: ""
							};
							this.$refs.addFormContent.close();
							this.$set(this.lists, this.index, res.data.data);
						}
					});
				} else {
					this.$ajax.post("v2/agent-users/bank-store", this.form1).then((res) => {
						if (res) {
							this.form1 = {
								id: 0,
								name: "",
								user_id: 0,
								bank_id: 0,
								payment_id: 0,
								account_type: 0,
								card_no: "",
								limint_min_amount: 0,
								limint_max_amount: 0,
								limint_day_amount: 0,
								limit_day_order_number: 0,
								payment_qrcode: "",
								payment_qrcode_url: ""
							};
							this.$refs.addFormContent.close();
							this.lists.unshift(res.data.data);
						}
					});
				}
			},
			open(index) {
				this.$set(this.lists[index], "show", this.lists[index].show == 1 ? 0 : 1);
			},
			selectStatus(item) {
				this.form.collection_status = item.id;
			},
			selectPayment(item) {
				this.form.account_type = item.id;
			},
			search() {
				this.$refs.paging.reload();
			},
			reset() {
				this.form = {
					account_type: 0,
					collection_status: -1,
					keyword: "",
				};
				this.$refs.paging.reload();
			},
			submit() {
				this.$refs.paging.reload();
			},
			openAddForm(id = 0) {
				if (id == 2) {
					this.form1.bank_id = 93;
				}
				if (id == 4) {
					this.form1.bank_id = 84;
				}
				if (id == 6) {
					this.form1.bank_id = 175;
				}
				this.form1.account_type = id;
				this.form1.payment_id = id;
				this.$refs.addMenu.close();
				this.$refs.addFormContent.open();
			},
			edit(item, index) {
				this.index = index;
				this.form1.id = item.id;
				this.form1.name = item.name;
				this.form1.bank_id = item.bank_id;
				this.form1.account_type = item.account_type;
				this.form1.payment_id = item.payment_id;
				this.form1.card_no = item.card_no;
				this.form1.limint_min_amount = item.limint_min_amount;
				this.form1.limint_max_amount = item.limint_max_amount;
				this.form1.limint_day_amount = item.limint_day_amount;
				this.form1.payment_qrcode = item.payment_qrcode;
				this.form1.limit_day_order_number = item.limit_day_order_number;
				this.form1.payment_qrcode_url = item.payment_qrcode_url;
				this.$refs.addFormContent.open();
			},
			deletefile(index) {
				this.form1.payment_qrcode = "";
				this.form1.payment_qrcode_url = "";
			},
			setStatus(item, index) {
				this.$ajax.get("v2/agent-users/set-status/" + item.id).then((res) => {
					if (res) {
						this.$set(this.lists[index], "collection_status_text", item.collection_status == 1 ?
							"收单关闭" : "收单开启");
						this.$set(this.lists[index], "collection_status", item.collection_status == 1 ? 0 : 1);
					}
				});
			},
		},
	};
</script>

<style lang="less" scoped>
	.content {
		min-height: 100vh;
		background-color: #1c1c1c !important;

		::v-deep .uni-popup {
			z-index: 999;
		}

		.formContent {
			width: 100%;
			background-color: #424242;
			height: 100vh;

			.block {
				display: flex;
				justify-content: space-between;
				align-items: center;
				width: 710rpx;

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
					color: hsla(0, 0%, 100%, 0.7);
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

		.addMenuContent {
			margin-left: 450rpx;
			margin-right: 20rpx;
			margin-top: 100rpx;
			background-color: #424242;
			border-radius: 8rpx;
			padding: 10rpx 20rpx;
			width: 250rpx;
			will-change: box-shadow;
			transition: box-shadow 0.28s cubic-bezier(0.4, 0, 0.2, 1), -webkit-box-shadow 0.28s cubic-bezier(0.4, 0, 0.2, 1);
			box-shadow: 0 3px 1px -2px rgba(0, 0, 0, 0.2), 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 1px 5px 0 rgba(0, 0, 0, 0.12);

			>.item {
				color: rgb(237, 209, 133);
				font-size: 28rpx;
				padding: 20rpx 10rpx;
			}
		}

		::v-deep .uni-popup {
			z-index: 999;
		}

		.password {
			width: 100%;
			background-color: #424242;

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

			.scroll-view-forms {
				height: calc(100vh - 110rpx);
			}

			.forms {
				padding: 40rpx 20rpx 240rpx 20rpx;

				>.item {
					>.help {
						font-size: 24rpx;
						color: #ffffff;
						margin-top: -30rpx;
						padding-bottom: 20rpx;
						padding-left: 10rpx;
						color: red;
					}
				}
			}
		}

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

		.searchs {
			display: flex;
			padding-right: 20rpx;

			>.del {
				align-self: center;

				>.text {
					height: 80rpx;
					font-size: 24rpx;
					border-radius: 8rpx;
					color: #424242;
					background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);
					align-self: center;
					width: 120rpx;
					display: flex;
					justify-content: center;
					align-items: center;
				}
			}
		}

		.bob-search {
			margin: 20rpx;
			flex: 1;

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
		}

		.main {
			margin: 20rpx;

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

						padding: 32rpx;
						color: rgba(0, 0, 0, 0.87);


						>.top-header {

							display: flex;
							justify-content: space-between;

							>.label {
								display: flex;
								justify-content: flex-start;
								align-items: center;

								>.name {
									font-weight: 900;
									font-size: 32rpx;
									color: rgba(0, 0, 0, 0.87);
								}

								>.sname {
									padding-top: 10rpx;
									font-size: 28rpx;
								}
							}

							>.top-right-info {
								display: flex;
								flex: 1;
								font-size: 24rpx;
								justify-content: flex-end;
								align-items: center;



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
										color: #ffffff;
										background-color: #121212;
										font-size: 28rpx;
										display: flex;
										justify-content: center;
										align-items: center;
										padding: 10rpx 20rpx;

										&.forbid {
											background-color: rgb(166, 166, 166);
										}
									}
								}

								>.img {
									margin-left: 10rpx;
									width: 48rpx;
									height: 48rpx;
									transform: rotate(180deg);
								}
							}

						}

						>.top-bottom-info {
							display: flex;
							flex-direction: column;
							flex: 1;

							>.label {
								display: flex;
								justify-content: flex-start;
								align-items: center;
								padding-bottom: 10rpx;

								>.name {
									font-weight: 900;
									font-size: 32rpx;
									color: rgba(0, 0, 0, 0.87);
								}

								>.sname {
									padding-top: 10rpx;
									font-size: 28rpx;
								}

								>.top-right-main {
									display: flex;
									flex-direction: column;
									align-self: center;

									>.name {
										text-align: center;
									}

									>.value {
										text-align: center;
									}

									>.status {
										border-radius: 32rpx;
										color: #ffffff;
										background-color: #121212;
										font-size: 28rpx;
										display: flex;
										justify-content: center;
										align-items: center;
										padding: 10rpx 20rpx;

										&.forbid {
											background-color: rgb(166, 166, 166);
										}
									}
								}
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
							justify-content: space-between;

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

							&.line {
								padding-top: 20rpx;
								margin-top: 20rpx;
								border-top: 2rpx solid rgba(255, 255, 255, 0.12);

								>.action {
									flex: 1;
									display: flex;
									justify-content: space-between;

									>.bt1 {
										padding-left: 32rpx;
										display: flex;

										.status {
											display: flex;
											justify-content: flex-start;
											align-items: center;

											::v-deep uni-switch .uni-switch-input {
												border: 2rpx solid rgba(237, 209, 133, 0.12) !important;
											}

											::v-deep uni-switch .uni-switch-input:before {
												background-color: #a6a6a6;
											}

											::v-deep uni-switch .uni-switch-input:after {
												width: 30px;
												background-color: #edd185 !important;
												box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
											}

											>.name {
												color: hsla(0, 0%, 100%, 0.7);
												font-size: 28rpx;
											}
										}

										>.del {
											padding-left: 30rpx;
										}
									}

									>.bt2 {
										align-self: center;
										padding-right: 32rpx;

										>.edit {
											padding: 10rpx 20rpx;
											border-radius: 56rpx;
											font-size: 24rpx;
											color: rgb(237, 209, 133);
											border: 2rpx solid rgb(237, 209, 133);
										}
									}
								}
							}

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
									transform: rotate(0deg);
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
