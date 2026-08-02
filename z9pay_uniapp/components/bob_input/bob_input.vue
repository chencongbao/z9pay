<template>
	<view class="bob-input" v-bind:class="{'focus':focus,'fill':inputValue}">
		<uni-transition ref="ani" mode="slide-up" :show="show" custom-class="custom-transition">
			<view class="label" v-on:click="onFocus">
				{{label}}
			</view>
		</uni-transition>
		<view class="name">
			{{label}}
		</view>
		<input class="input" :type="type" v-on:input="onInput" v-model="inputValue"
			placeholder-style="color: hsla(0, 0%, 100%, .7)" v-on:focus="onFocus" v-on:blur="onBlur" ref="input" placeholder=""/>

	</view>
</template>

<script>
	export default {
		name: "bob_input",
		props: {
			label: {
				type: String,
				default: ""
			},
			value: {
				type: [String, Number],
				default: ""
			},
			type:{
				type:String,
				default:"text"
			}
		},
		data() {
			return {
				show: true,
				focus: false,
				inputValue: this.value
			};
		},
		methods: {
			onFocus() {
				this.focus = true;
				this.$refs.input.focus();
				this.$refs.ani.step({
					translateX: "0",
					translateY: '-70rpx'
				})
				this.$refs.ani.run();
			},
			onBlur() {
				this.focus = false;
				if (this.$isempty(this.inputValue)) {
					this.$refs.ani.step({
						translateY: '-20rpx'
					})
					this.$refs.ani.run();
				}
			},
			onInput() {
				this.$emit('input', this.inputValue)
			}
		}
	}
</script>

<style lang="less" scoped>
	.bob-input {
		border: 2rpx solid rgba(255, 255, 255, 0.24);
		height: 100rpx;
		margin-bottom: 40rpx;
		border-radius: 8rpx;
		position: relative;
		padding-left: 20rpx;
		padding-right: 20rpx;

		::v-deep .custom-transition {
			position: absolute;
			left: 20rpx;
			top: 50%;
			transform: translate(0, -50%);
			z-index:10;
		}

		.label {
			color: hsla(0, 0%, 100%, .7);
			font-size: 28rpx;
			background-color: #424242;
			padding-left: 10rpx;
			padding-right: 10rpx;
		}
		
		.name {
			color: hsla(0, 0%, 100%, .7);
			font-size: 28rpx;
			background-color: #424242;
			padding-left: 10rpx;
			padding-right: 10rpx;
			display: none;
		}

		>.input {
			width: 100%;
			height: 100rpx;
			border: none;
			outline: none;
			color: #ffffff;
			font-size: 28rpx;
			background-color: transparent;
			z-index: 1000;
			
		}

		&.focus {
			border: 4rpx solid #edd185;
		}
		
		&.fill{
			.custom-transition{
				display: none;
			}
			.name{
				position: absolute;
				left: 20rpx;
				top: -20rpx;
				z-index:10;
				display: block;
			}
		}
	}
</style>