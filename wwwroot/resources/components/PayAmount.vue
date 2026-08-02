<template>
    <div class="section">
        <div class="alert-title">
            {{ $t("cashier.amount_tip") }}
        </div>
        <!-- 金额 -->
        <div class="amount">
            <span class="currency">{{currency}}</span>
            <span class="value">{{ amount }}</span>
        </div>
    </div>
</template>

<script>
export default {
    name: "PayAmount",
    props: {
        amount: {type: [String, Number], required: true},
        currency: {type: String, default: "¥"},
    }
}
</script>

<style lang="less" scoped>
.alert-title {
    color: #e53935;
    text-align: center;
    font-weight: 700;
    margin-bottom: 6px;
}

.amount {
    text-align: center;
    margin-bottom: 12px;
}

.currency {
    font-size: 20px;
}

.value {
    font-size: 34px;
    font-weight: 800;
}
</style>
