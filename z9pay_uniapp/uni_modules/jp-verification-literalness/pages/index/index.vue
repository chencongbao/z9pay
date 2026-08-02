<template>
  <view class="content">
	<view class="hader">基础用法</view>
	<jp-verification-literalness></jp-verification-literalness>
	<view class="hader">通过接口获取验证码</view>
	<jp-verification-literalness @getCode="getCode" :securityCode="securityCode"></jp-verification-literalness>
	<view class="hader"> 自定义验证码数量 - 自定义颜色</view>
	<jp-verification-literalness backgroundColor="#a7c3ff"  :colorList="colorList" :lineColorList="lineColorList"  :codeLength="6" ></jp-verification-literalness> 
    <view class="hader"> 验证码大小 - 干扰线数量</view>
    <jp-verification-literalness :contentHeight="100" :contentWidth="200" :lineLength="60"></jp-verification-literalness>
  </view>
</template>
<script>
export default {
  data() {
    return {
		val:'',
		securityCode:'我是获取的',
		lineColorList: ['rgba(0, 85, 255, 0.5)','rgba(85, 255, 0, 0.5)','rgba(255, 255, 0, 0.5)'],
		colorList:['rgb(255, 0, 0)','rgb(255, 0, 127)','rgb(255, 0, 255)']
    };
  },
  methods:{
	  getCode(){
		this.securityCode = '重新获取了'  
	  }
  }
};
</script>
<style scoped lang="scss">
	.content {
		padding: 0 20px;
		.hader {
			line-height: 80rpx;
			font-weight: 800;
		}
	}
</style>