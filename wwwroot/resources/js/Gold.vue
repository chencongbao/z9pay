<template>
    <div id="app" class="bob-app">
        <template v-if="step == 0">
            <van-icon name="gem-o" color="#F99D21" size="3rem"/>
            <div class="name">黄金</div>
            <van-loading type="spinner" color="#000000"/>
        </template>
        <Empty :message="msg" :type="false" v-else/>
    </div>
</template>

<script>
import Empty from "../components/Empty.vue";
export default {
    components: {
        Empty
    },
    data(){
        return  {
            ordernumber: bob_ordernumber,
            step:0,
            msg: "未知错误",
            msgType: false,
        }
    },
    created() {
        if(!this.isAlipayApp()){
            this.error("请在支付宝APP中打开");
            return;
        }
        this.$ajax
            .get("/cashier/deposit/gold/order", {
                params: { ordernumber: this.ordernumber },
            })
            .then((res) => {
                if (res.code == 200) {
                    window.location.href = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='+JSON.stringify(res.data);
                } else {
                    this.error(res.message);
                }
            })
            .catch((error) => {
                this.error(error);
            });
    },
    methods:{
        isAlipayApp() {
            var userAgent = navigator.userAgent;
            return /AlipayClient|Alipay/.test(userAgent);
        },
        error(msg) {
            this.step = 1;
            this.msg = msg;
            this.msgType = false;
        },
        success(msg) {
            this.step = 1;
            this.msg = msg;
            this.msgType = true;
        },
    }
}

</script>

<style lang="less">
.bob-app{
    height: calc(100vh - 100px);
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    >.name{
        padding-top: 10px;
        font-size: 18px;
        font-weight: bold;
        padding-bottom: 50px;
    }
}
</style>
