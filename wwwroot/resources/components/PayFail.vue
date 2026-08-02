<template>
    <pay-wrapper :name="$config.payment_name">
        <div class="fail-card">
            <div class="fail-icon">
                <van-icon name="close"/>
            </div>

            <div class="fail-title">{{ message || $t('cashier.unknown_error') }}</div>

            <div class="divider"></div>

            <div class="order">
                <span class="order-no">{{ $maskedString($config.ordernumber) }}</span>
                <pay-copy :text="$config.ordernumber"/>
            </div>
        </div>
    </pay-wrapper>
</template>

<script>
export default {
    name: "PayFail",
    props: {
        message: {type: String, default: ''},
    }
};
</script>

<style lang="less" scoped>
.fail-card {
    background: #fff;
    padding: 16px;
    text-align: center;
    width: auto;

    .fail-icon {
        width: 72px;
        height: 72px;
        background: #fde8e7;
        border-radius: 50%;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;

        .van-icon {
            font-size: 42px;
            color: #e53935;
        }
    }

    .fail-title {
        color: #e53935;
        font-size: 22px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .fail-msg {
        color: #333;
        font-size: 15px;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .divider {
        height: 1px;
        background: #eee;
        margin: 10px 0 16px;
    }

    .order {
        display: flex;
        align-items: center;
        justify-content:space-between;
        flex-wrap: wrap;
        gap: 8px;

        .order-label {
            color: #111;
            font-weight: 600;
            font-size: 14px;
        }

        .order-no {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            color: #333;
            font-size: 14px;
        }

        .copy-btn {
            height: 30px;
            border-radius: 6px;
            font-size: 12px;
            padding: 0 10px;
        }
    }
}

/* 动画 */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
