<script>
	import { getDepositNotice,getTransferNotice} from "@/static/js/funtions";
	export default {
		onLaunch: function() {
			if(this.$store.state.login){
				this.$store.commit("watchTransferNotice",getTransferNotice());
				this.$store.commit("watchDepositNotice",getDepositNotice());
				if(this.$store.state.agent == 1){
					uni.setTabBarItem({index:1,visible:false});
					uni.setTabBarItem({index:2,visible:false});
					uni.setTabBarItem({index:3,visible:true});
					uni.setTabBarItem({index:4,visible:true});
				}else{
					uni.setTabBarItem({index:3,visible:false});
					uni.setTabBarItem({index:4,visible:false});
					uni.setTabBarItem({index:1,visible:true});
					uni.setTabBarItem({index:2,visible:true});
				}
				
			}
		},
		onShow: function() {
			console.log('App Show')
		},
		onHide: function() {
			console.log('App Hide')
		}
	}
</script>

<style lang="scss">
	@import "uview-ui/index.scss";
</style>

<style>
	@import "@/static/iconfont/iconfont.css";

	html {
		background: #121212 !important;
	}

	@keyframes mescrollRotate {
		from {
			transform: rotate(0deg);
		}

		to {
			transform: rotate(360deg);
		}
	}
</style>