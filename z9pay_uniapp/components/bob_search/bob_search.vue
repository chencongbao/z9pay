<template>
	<view class="bob-search">
		<image src="../../static/img/img4.svg" mode="" class="img" v-on:click="$refs.searchPopup.open()" v-if="searchModal"></image>
		<view class="forms">
			<view class="inputs">
				<uni-easyinput @focus="searchInputFocus = true" @blur="onBlur" :placeholder="placeholder" class="input" trim="both" :styles="styles" :placeholderStyle="searchInputPlaceholderColor" primaryColor="#edd185" v-model="keyword" @input="onInput" @clear="clear"></uni-easyinput>
			</view>
			<view class="button" v-bind:class="{'active':searchInputFocus}" v-on:click="search">
				<uni-icons type="search" color="rgb(114, 114, 114)" size="24"></uni-icons>
			</view>
		</view>
		<image src="../../static/img/img6.png" mode="" class="img1" v-if="message"></image>

		<uni-popup ref="searchPopup" type="top" maskBackgroundColor="transparent" :animation="false">
			<view class="searchPopup">
				<view class="search-content">
					<slot></slot>
				</view>
				<view class="btns">
					<view class="reset" v-on:click="reset">
						重置
					</view>
					<view class="submit" v-on:click="submit">
						查询
					</view>
				</view>
			</view>
		</uni-popup>
	</view>
</template>

<script>
	export default {
		name: "bob_search",
		props: {
			value: {
				type: [String, Number],
				default: ""
			},
			message: {
				type: Boolean,
				default: false
			},
			placeholder: {
				type: String,
				default: "搜索订单号"
			},
			searchModal: {
				type: Boolean,
				default: true
			}
		},
		data() {
			return {
				keyword: this.value,
				styles: {
					color: "#edd185"
				},
				searchInputFocus: false
			};
		},
		computed: {
			searchInputPlaceholderColor() {
				if (this.searchInputFocus) {
					return "font-size:28rpx;color:#edd185";
				}
				return "font-size:28rpx;color:#999999";
			}
		},
		methods: {
			clear() {
				this.keyword = "";
				this.$emit('input', this.keyword);
				this.$emit('search');
			},
			search() {
				this.$emit('search');
			},
			reset() {
				this.$emit('reset');
				this.$refs.searchPopup.close();
			},
			submit() {
				this.$emit('submit');
				this.$refs.searchPopup.close();
			},
			onInput() {
				this.$emit('input', this.keyword)
			},
			onBlur() {
				this.searchInputFocus = false;
				this.$emit('input', this.keyword);
			}
		}
	}
</script>

<style lang="less" scoped>
	.bob-search {
		display: flex;
		justify-content: center;
		align-items: center;

		.searchPopup {
			margin-left: 20rpx;
			margin-right: 20rpx;
			margin-top: 200rpx;
			background-color: #424242;
			border-radius: 8rpx;
			padding: 32rpx 20rpx;
			min-width: 272rpx;
			max-width: 75%;
			will-change: box-shadow;
			transition: box-shadow .28s cubic-bezier(.4, 0, .2, 1), -webkit-box-shadow .28s cubic-bezier(.4, 0, .2, 1);
			box-shadow: 0 3px 1px -2px rgba(0, 0, 0, .2), 0 2px 2px 0 rgba(0, 0, 0, .14), 0 1px 5px 0 rgba(0, 0, 0, .12);

			>.search-content {
				>.search {
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
			}

			>.btns {
				display: flex;
				justify-content: flex-end;
				align-items: center;
				font-size: 28rpx;
				padding-top: 32rpx;
				padding-bottom: 20rpx;
				padding-right: 32rpx;

				>.reset {
					color: #ffffff;
				}

				>.submit {
					padding-left: 80rpx;
					color: #edd185;
				}
			}
		}


		>.img1 {
			width: 80rpx;
			height: 80rpx;
		}

		>.img {
			width: 56rpx;
			height: 48rpx;
			padding-right: 20rpx;
		}

		>.forms {
			flex: 1;
			height: 76rpx;
			background-color: #121212;
			display: flex;
			justify-content: center;
			align-items: center;
			border-radius: 8rpx;

			>.inputs {
				height: 76rpx;
				flex: 1;
				padding-left: 20rpx;

				::v-deep .is-input-border {
					border: 1px solid #121212 !important;
					background-color: #121212 !important;
				}

				::v-deep .uni-easyinput__content-input {
					height: 76rpx !important;
					font-size: 28rpx !important;
				}

				>.input {
					color: #edd185;
					width: 100%;
					height: 76rpx;
					border: none;
					outline: none;
					font-size: 28rpx;
					background-color: #121212;
					background-color: none !important;
				}
			}

			>.button {
				width: 100rpx;
				height: 56rpx;
				background-color: #1c1c1c;
				display: flex;
				justify-content: center;
				align-items: center;
				margin-right: 14rpx;
				border-radius: 8rpx;

				&.active {
					background-color: #edd185;
				}
			}

		}
	}
</style>