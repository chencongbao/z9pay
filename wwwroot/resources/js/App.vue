<template>
    <div id="app" class="bob-app">
        <Transition mode="out-in">
            <van-loading type="spinner" v-if="step == -1"/>

            <component :is="impl.TrueName" v-else-if="step == 1" :info="payinfo" v-on:submit="submit"/>
            <component :is="impl.Cashier" v-else-if="step == 2" :info="payinfo" v-on:submit="submit"/>

            <pay-success v-else-if="step == 3" :message="msg"/>
            <pay-fail v-else :message="msg"/>
        </Transition>
    </div>
</template>
<script>
import {resolveCashierImpl} from '@/common/cashierRegistry';

const TRUE_NAME_WAIT_RULES = [
    {
        currency: 'PKR',
        app_name: 'lupay',
        payment: ['easypaisa', 'jazzcash'],
    },
    {
        currency: 'VND',
        payment: ['card_pc'],
    },
];

export default {
    data() {
        return {
            step: -1,
            msg: "",
            payinfo: {},
            impl: {TrueName: null, Cashier: null},
        };
    },
    async created() {
        const found = resolveCashierImpl({
            currency: this.$config.currency,
            code: this.$config.payment,
            system: this.$config.app_name
        });
        this.impl.TrueName = found.TrueName;
        this.impl.Cashier = found.Cashier;
        this.$ajax.get("/cashier/deposit/order", {
            params: {ordernumber: this.$config.ordernumber},
        }).then((res) => {
            if (res.code == 200) {
                this.payinfo = res.data;
                if (res.data.status == 1) {
                    this.step = 1;
                } else if (res.data.status == 3) {
                    if (this.shouldWaitOnTrueName()) {
                        this.step = 1;
                    } else if (res.data.channel_pay_url) {
                        window.location.replace(res.data.channel_pay_url);
                    } else {
                        this.step = 2;
                    }
                } else {
                    this.error(this.$t('cashier.order_close'));
                }
            } else {
                this.error(res.message);
            }
        }).catch((error) => {
            this.error(error);
        });
    },
    methods: {
        submit(data) {
            if (data.step == 2) {
                this.step = data.step;
                this.payinfo = data;
            }else if(data.step == 3){
                this.success(data.msg);
            } else {
                this.error(data.msg);
            }
        },
        error(msg) {
            this.step = 4;
            this.msg = msg;
        },
        success(msg) {
            this.step = 3;
            this.msg = msg;
        },
        shouldWaitOnTrueName() {
            const config = {
                currency: this.$config.currency,
                app_name: this.$config.app_name,
                payment: this.$config.payment,
            };

            return TRUE_NAME_WAIT_RULES.some(rule => this.matchesTrueNameWaitRule(config, rule));
        },
        matchesTrueNameWaitRule(config, rule) {
            return this.matchesRuleValue(config.currency, rule.currency)
                && this.matchesRuleValue(config.app_name, rule.app_name)
                && this.matchesRuleValue(config.payment, rule.payment);
        },
        matchesRuleValue(value, expected) {
            if (expected === undefined || expected === null || expected === '*') {
                return true;
            }

            const normalizedValue = String(value || '').toLowerCase();
            const expectedValues = Array.isArray(expected) ? expected : [expected];

            return expectedValues
                .map(item => String(item || '').toLowerCase())
                .includes(normalizedValue);
        },
    },
};
</script>
<style lang="less">
#app {
    margin: 0px auto;
}

/* 针对移动端的样式 */
@media (max-width: 768px) {
    #app {
        max-width: 100%;
    }
}

/* 针对PC端的样式 */
//@media (min-width: 769px) {
//    #app {
//        max-width: 750px;
//    }
//}
</style>
