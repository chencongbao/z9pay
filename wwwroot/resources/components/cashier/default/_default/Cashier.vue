<template>
    <pay-wrapper :name="$config.payment_name" v-if="!$isEmpty(info)">
        <pay-amount :amount="info.amount" />
        <important-alert/>
        <div class="divider"></div>
        <pay-collection-info :info="info"/>
        <CountdownTimer :seconds="info.time" :minite="info.minite" v-on:finish="action"/>
        <section class="section">
            <div class="row">
                <span class="row__label">{{ $maskedString($config.ordernumber) }}</span>
                <pay-copy :text="$config.ordernumber"/>
            </div>
        </section>
        <bottom-bar-actions v-on:finish="action"/>
    </pay-wrapper>
</template>


<script>
export default {
    name: 'Cashier',
    emits: ['submit'],
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        }
    },
    methods: {
        action(obj){
            this.$emit('submit',obj);
        }
    }
}
</script>

<style lang="less" scoped>

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f7f8fa;
    border-radius: 8px;
    padding: 10px 10px;
    margin-bottom: 8px;
}

.row__label {
    color: #222;
    word-break: break-all;
}

.divider {
    height: 1px;
    background: #e9eef5;
    margin: 10px 0 8px;
}


</style>
