<template>
    <section class="alert-box">
        <div class="alert-box__title">
            <span class="icon">⚠️</span>{{$t('cashier.important_reminder')}}
        </div>
        <ul class="alert-box__list">
            <li class="alert-box__item">
                This QR Code is powered by QRPh which can also be scanned by partner <b>Banks and e-Wallets</b>
            </li>
        </ul>
    </section>
</template>

<script>
export default {
    name: "ImportantAlert",
};
</script>

<style lang="less" scoped>
.alert-box {
    margin: 10px 0 6px;
    padding: 12px 12px 10px;
    border-radius: 10px;
    background: #fff5f5;
    border: 1px solid #ffb3b3;
    box-shadow: 0 4px 14px rgba(255, 59, 48, 0.12);

    &__title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 800;
        color: #d93025;
        font-size: 14px;
        margin-bottom: 6px;

        .icon {
            font-size: 16px;
        }
    }

    &__list {
        margin: 0;
        padding-left: 0;
        list-style: none;
    }

    &__item {
        color: #d93025;
        font-size: 13px;
        line-height: 1.6;
        padding: 2px 0;
        .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #d93025;
            margin-right: 8px;
            margin-top: 6px;
            flex-shrink: 0;
        }

        b {
            font-weight: 700;
        }
    }
}
</style>
