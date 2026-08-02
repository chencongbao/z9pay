<template>
	<view class="bob-select" v-bind:class="{'focus':focus,'fill':inputValue}">
		<uni-transition ref="ani" mode="slide-up" :show="show" custom-class="custom-transition">
			<view class="label" v-on:click="onFocus">
				{{label}}
			</view>
		</uni-transition>
		<view class="name">
			{{label}}
		</view>
		<input class="input" :type="type" v-on:input="onInput" v-model="inputValue"
			placeholder-style="color: hsla(0, 0%, 100%, .7)" v-on:focus="onFocus" v-on:blur="onBlur" ref="input"
			placeholder=""/>
		<uni-icons custom-prefix="iconfont" type="icon-xiangxia" size="20" class="icon" color="#ffffff"></uni-icons>
		<view class="select-option" v-if="doptions.length > 0 && focus">
			<view class="select-item" v-bind:class="{'selected':vo.selected == 1}" v-for="(vo,index) in doptions" v-bind:key="index" v-on:click.stop="selectdItem(vo)">
				{{vo.name}}
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "bob_select",
		props: {
			options:{
				type:Array,
				default:function(){
					return [];
				}
			},
			label: {
				type: String,
				default: ""
			},
			value: {
				type: [String, Number],
				default: ""
			},
			type: {
				type: String,
				default: "text"
			}
		},
		data() {
			return {
				doptions:[],
				show: true,
				focus: false,
				inputValue: "",
				selectedId:0,
				blurTimer:null
			};
		},
		beforeDestroy() {
			if(this.blurTimer) clearTimeout(this.blurTimer);
		},
		watch:{
			value:{
				handler(newVal,oldVal){
					if(newVal > 0 && this.options.length > 0){
						this.options.map((item)=>{
							if(newVal == item.id){
								this.inputValue = item.name;
								item.selected = 1;
								this.selectedId = item.id;
							}
						});
					}
				},
				immediate:true,
				deep:true
			},
			options:{
				handler(newVal,oldVal){
					if(newVal.length > 0){
						this.doptions = newVal;
					}
				},
				immediate:true,
				deep:true
			}
		},
		methods: {
			onFocus() {
				if(this.blurTimer) {
					clearTimeout(this.blurTimer);
					this.blurTimer = null;
				}
				this.focus = true;
				this.$refs.input.focus();
				this.$refs.ani.step({
					translateX: "0",
					translateY: '-70rpx'
				})
				this.$refs.ani.run();
			},
			onBlur(e) {
				if(this.blurTimer) clearTimeout(this.blurTimer);
				this.blurTimer = setTimeout(()=>{
					this.blurTimer = null;
					if(this.selectedId == 0){
						this.inputValue = '';
					}
					this.doptions = this.options;
					if (this.$isempty(this.inputValue)) {
						if(!this.$refs.ani) return;
						this.$refs.ani.step({
							translateY: '-20rpx'
						})
						this.$refs.ani.run();
					}
					this.focus = false;
				},250);
			},
			onConfirm(){
				this.$emit('input', this.inputValue);
			},
			selectdItem(item){
				this.focus = false;
				this.doptions = this.options;
				this.doptions.map(item=>{
					item.selected = 0;
					return item;
				});
				this.inputValue = item.name;
				this.selectedId = item.id;
				this.$emit('input', item.id);
			},
			onInput(e) {
				if(!this.focus)return;
				this.selectedId = 0;
				let noptions = this.options.filter(item=>{
					return item.name.indexOf(e.target.value) != -1;
				});
				this.doptions = noptions;
			}
		}
	}
</script>

<style lang="less" scoped>
	.bob-select {
		border: 2rpx solid rgba(255, 255, 255, 0.24);
		height: 100rpx;
		margin-bottom: 40rpx;
		border-radius: 8rpx;
		position: relative;
		padding-left: 20rpx;
		padding-right: 100rpx;

		>.icon {
			position: absolute;
			right: 20rpx;
			top: 50%;
			transform: translate(0, -50%);
		}

		>.select-option {
			padding: 20rpx;
			left:0rpx;
			right: 0rpx;
			top:110rpx;
			display: block;
			z-index: 10000;
			max-height: 450rpx;
			position: absolute;
			display: inline-block;
			border-radius: 8rpx;
			padding-bottom: 50rpx;
			overflow-y: auto;
			overflow-x: hidden;
			contain: content;
			background-color: #424242;
			box-shadow: 0 10rpx 10rpx -6rpx rgba(0, 0, 0, .2), 0 16rpx 20rpx 2rpx rgba(0, 0, 0, .14), 0 6rpx 28rpx 4rpx rgba(0, 0, 0, .12);
			
			>.select-item{
				color: #ffffff;
				min-height: 96rpx;
				padding: 0 32rpx;
				font-size: 32rpx;
				line-height: 96rpx;
				
				&.selected{
					background-color: rgba(237,209,133, .12);
					color: #edd185;
				}
			}
			
			
		}
		
		>.closebtn{
			position: absolute;
			left:0rpx;
			right: 0rpx;
			top:510rpx;
			z-index: 1000000;
		}



		::v-deep .custom-transition {
			position: absolute;
			left: 20rpx;
			top: 50%;
			transform: translate(0, -50%);
			z-index: 10;
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

		&.fill {
			.custom-transition {
				display: none;
			}

			.name {
				position: absolute;
				left: 20rpx;
				top: -20rpx;
				z-index: 10;
				display: block;
			}
		}
	}
</style>
