<template>
	<uni-popup ref="detailPopup" type="center" class="bob-detail">
		<view class="sitem-detail">
			<uni-card :title="title">
				<view class="card-content">
					<slot>
						<view class="item" v-for="(vo,index) in rows" v-bind:key="index">
							<view class="label">
								{{vo.name}}
							</view>
							<template v-if="!vo.copy">
								<view class="value" v-if="vo.image" v-on:click="$preview(vo.value)">查看</view>
								<view class="value" v-else>{{vo.value}}</view>
							</template>
							
							<view class="value copy" v-else v-on:click="$copy(vo.value)">
								<span>{{vo.value}}</span>
								<uni-icons type="icon-fuzhi" custom-prefix="iconfont" color="#e8bd70" class="icon"></uni-icons>
							</view>
						</view>
					</slot>
					<slot name="footer">
						<view class="close" v-on:click="close">
							<span>关闭</span>
						</view>
					</slot>
				</view>
			</uni-card>
		</view>
	</uni-popup>
</template>

<script>
	export default {
		name: "bob_detail",
		props: {
			title:{
				type:String,
				default:"订单详情"
			},
			rows: {
				type: Array,
				default: function() {
					return []
				}
			}
		},
		data() {
			return {

			};
		},
		methods: {
			open() {
				this.$refs.detailPopup.open();
			},
			close() {
				this.$refs.detailPopup.close();
			}
		}
	}
</script>

<style lang="less" scoped>
	.bob-detail {
		.sitem-detail {
			width: 750rpx;

			::v-deep .uni-card {
				background-color: #424242 !important;
				border-color: #424242 !important;
			}

			::v-deep .uni-card .uni-card__header {
				border-bottom: 2rpx hsla(0, 0%, 100%, .12) solid !important;
			}

			::v-deep .uni-card .uni-card__header .uni-card__header-content .uni-card__header-content-title {
				font-size: 40rpx !important;
				color: #e8bd70 !important;
			}

			.card-content {
				padding: 10rpx;
				color: hsla(0, 0%, 100%, .7);
				font-size: 28rpx;

				>.item {
					display: flex;
					justify-content: space-between;
					padding-bottom: 20rpx;
					>.value {
						>.link{
							color: #e8bd70;
							font-size: 28rpx;
						}
						&.copy {
							display: flex;
							>.span {
								padding-right: 10rpx;
							}
							>.icon{
								padding-left: 10rpx;
							}
						}
					}
				}

				>.close {
					padding-top: 20rpx;
					display: flex;
					justify-content: flex-end;
					color: #edd185;
					font-size: 28rpx;
				}
			}

		}
	}
</style>