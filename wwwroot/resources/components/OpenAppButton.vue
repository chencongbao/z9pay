<template>
    <section class="section" v-if="info.collection_app_link && $isPhone()">
        <van-button class="jump-btn" :url="info.collection_app_link">
            {{$t("cashier.open_app_pay")}}
        </van-button>
    </section>
</template>

<script>
export default {
    name: "OpenAppButton",
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        },
    }
}
</script>

<style lang="less" scoped>
/* 跳转按钮 */
.jump-btn {
    width: 100%;
    background: #ff3b30;
    color: #fff;
    font-weight: 700;
    border: none;
    height: 44px;
    border-radius: 8px;
    cursor: pointer;
}
</style>
