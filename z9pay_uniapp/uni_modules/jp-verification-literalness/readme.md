# 
#  # 欢迎使用 layout jp-verification-literalness組件
**jp-verification-literalness組件，是验证码组件。
用于登录验证，获取其他需要验证的地方，可以起前端自动生成验证码，或通过接口调用验证码，自定义验证框颜色。用法简单

### 安装方式
本组件符合[easycom](https://uniapp.dcloud.io/collocation/pages?id=easycom)规范，`HBuilderX 2.5.5`起，只需将本组件导入项目，在页面`template`中即可直接使用，无需在页面中`import`和注册`components`。

##有项目需要开发的请联系 QQ:371524845
###开发不易，如果帮助到你的，请支持 有问题请留言，作者会积极更新

###项目实例请查看 @/jp-layout/pages/index/index.vue

#使用方法
##### layout基础用法
```html
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
```

###方法介绍 
####参数
| 参数名        | 类型   |  默认值  | 说明  |
| --------   |  -------- |  --------| --------|
|   securityCode   |  securityCode  |   ''    |  验证码值，如果是通过接口调用的验证码需要在这里传入，不传入则自动生成验证码     |
|   codeLength   | Number  |   4    |  自动生成验证码数量 |
|   contentWidth   | Number  |   120    |   画板宽度（px)    |
| contentHeight        |   Number   |  60   |  画板高度（px)       |
| lineLength        |    Number    |  8 | 干扰线数量 |
| backgroundColor        |    String    |  'rgb(238,226,224)' |  触底加载触发距离 |
| lineColorList        |  Array     |  ['rgba(238,0,0,.5)','rgba(0, 170, 255,.5)','rgba(0, 170, 0,.5)','rgba(0, 0, 0,.5)','rgba(153, 146, 255,.5)']  | 干扰线可选颜色组（颜色会随机选择这些颜色，需要固定颜色传一种颜色即可）  |
| colorList        |    Array    |  ['rgb(238,0,0)','rgb(0, 170, 255)','rgb(0, 170, 0)','rgb(0, 0, 0)','rgb(153, 146, 255)'] |  文字可选颜色组（颜色会随机选择这些颜色，需要固定颜色传一种颜色即可） |

####方法
| 方法名        |   返回值 | 说明  |
| --------   |  --------|--------|
|  verification  | function（）   | 验证验证码是否正确，正确返回true   | 

####事件
| 方法名        |   返回值 | 说明  |
| --------   |  --------|--------|
|   @getCode  | function（）   |当传入securityCode时点击会回调该方法，用户可以在这里通过接口获取验证码   | 

