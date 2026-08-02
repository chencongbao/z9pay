<template>
    <pay-wrapper :name="$config.payment_name">
        <div class="success-card">
            <div class="success-icon">
                <van-icon name="checked"/>
            </div>
            <div class="success-title">{{ message }}</div>

            <div class="divider"></div>

            <div class="info-list">
                <div class="order">
                    <span class="order-no">{{ $maskedString($config.ordernumber) }}</span>
                    <pay-copy :text="$config.ordernumber"/>
                </div>
            </div>
        </div>
    </pay-wrapper>
</template>

<script>
export default {
    name: 'Cashier',
    props: {
        message: {type: String, default: 'Success'},
    }
};
</script>

<style lang="less" scoped>
.success-card {
    margin: 24px auto 0;
    background: #fff;
    padding: 36px 26px 30px;
    text-align: center;

    .success-icon {
        width: 72px;
        height: 72px;
        background: #e6f9ec;
        border-radius: 50%;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;

        .van-icon {
            font-size: 42px;
            color: #34a853;
        }
    }

    .success-title {
        color: #34a853;
        font-size: 22px;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .success-msg {
        color: #333;
        font-size: 15px;
        line-height: 1.6;
        margin-bottom: 18px;
    }

    .divider {
        height: 1px;
        background: #eee;
        margin: 10px 0 16px;
    }

    .info-list {
        text-align: left;
        margin-bottom: 20px;

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

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 14px;

            .label {
                color: #666;
            }

            .value {
                color: #111;
                font-weight: 600;
            }
        }
    }

    .btn-wrap {
        margin-top: 16px;

        .finish-btn {
            height: 44px;
            border-radius: 8px;
            background: #1f6ef2;
            border-color: #1f6ef2;
            font-weight: 700;
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
