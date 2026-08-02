<template>
    <van-button
        size="normal"
        plain
        color="#2a55ff"
        class="copy-btn"
        v-clipboard:copy="text"
        v-clipboard:success="onCopy"
        v-clipboard:error="onError"
    >
        {{ btnText || $t("cashier.copy") }}
    </van-button>
</template>
<script>
import {Toast} from "vant";

export default {
    name: 'PayCopy',
    props: {
        text: {type: [String, Number], required: true},
        btnText: {
            type: String, default: ""
        },
    },
    methods: {
        onCopy() {
            Toast(this.$t("cashier.copy_success"));
        },
        onError() {
            Toast(this.$t("cashier.copy_fail"));
        }
    }
}
</script>

<style scoped>
.copy-btn {
    height: 30px;
    border-radius: 6px;
    font-size: 12px;
    padding: 6px 10px;
    background: #eef1ff;
}
</style>
