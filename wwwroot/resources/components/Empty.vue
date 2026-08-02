<template>
    <div class="empty">
        <van-icon name="warning-o" color="#E8A00F" size="100" v-if="!type" />
        <van-icon name="certificate" v-else size="100" color="#E8A00F" />
        <div class="message">{{ message }}</div>
        <div class="line"></div>
    </div>
</template>
<script>
export default {
    props: {
        message: {
            type: String,
            default: "错误信息",
        },
        type: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {};
    },
};
</script>

<style lang="less">
.empty {
    padding-top: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    > .message {
        color: #333;
        padding-top: 20px;
        font-size: 20px;
    }
    > .line {
        border-bottom: 1px solid #eee;
        padding-top: 50px;
        height: 2px;
        width: 100%;
    }
}
</style>
