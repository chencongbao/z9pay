<template>
	<view class="tab-menu">
		<view class="item" v-for="(vo,index) in smenus" v-bind:key="index" v-bind:class="{'active':vo.active == 1}"
			v-on:click="clickTab(vo,index)">
			{{vo.name}}
		</view>
	</view>
</template>

<script>
	export default {
		name: "bob_tab",
		props: {
			menus: {
				type: Array,
				default: function() {
					return []
				}
			}
		},
		data() {
			return {
				smenus: []
			};
		},
		watch: {
			menus: {
				handler(newVal, oldVal) {
					if (newVal.length > 0) {
						this.smenus = newVal;
					}
				},
				immediate: true,
				deep: true
			}
		},
		methods: {
			clickTab(item, index) {
				this.smenus.map((value, sindex) => {
					value.active = 0;
					if(index == sindex){
						value.active = 1;
					}
				});
				this.$emit("tab", item.id);
			}
		}
	}
</script>

<style lang="less" scoped>
	.tab-menu {
		display: flex;
		background-color: #121212;
		border-radius: 8rpx;
		height: 76rpx;
		margin-top: 10rpx;
		margin-left: 20rpx;
		margin-right: 20rpx;

		>.item {
			flex: 1;
			text-align: center;
			color: rgba(255, 255, 255, 0.6);
			font-size: 28rpx;
			line-height: 76rpx;
			vertical-align: middle;

			&:last-child {
				border-top-right-radius: 8rpx;
				border-bottom-right-radius: 8rpx;
			}

			&:first-child {
				border-top-left-radius: 8rpx;
				border-bottom-left-radius: 8rpx;
			}

			&.active {
				color: #424242;
				background: linear-gradient(45deg, #e8bd70, #e8bd70 15%, #edd185 85%, #edd185);


			}
		}
	}
</style>