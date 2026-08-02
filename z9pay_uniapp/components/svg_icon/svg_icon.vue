<template>
	<svg class="icon" :style="cssVars">
		<use :xlink:href="iconName"></use>
	</svg>
</template>

<script>
	export default {
		name: 'IconSvg',
		props: {
			name: String,
			color: {
				type: String,
				default: "#fb8c00"
			},
			width: {
				type: String,
				default: "48"
			},
			height: {
				type: String,
				default: "48"
			}
		},
		computed: {
			iconName() {
				return `#${this.name}`
			},
			cssVars() {
				return {
					'--color': this.color,
					'--width':uni.upx2px(this.width)+"px",
					'--height':uni.upx2px(this.height)+"px"
				};
			}
		}
	}
</script>

<style lang="less" scoped>
	.icon {
		height: var(--height);
		width: var(--width);
		display: block;
		fill: var(--color);
	}
</style>