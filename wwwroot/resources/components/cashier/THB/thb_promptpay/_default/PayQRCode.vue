<template>
    <div class="qr-block">
        <!-- 上方操作提示 -->
        <div class="qr-text">
            ↓↓↓ ใช้ครั้งเดียวเท่านั้น กรุณาจ่ายเพียงครั้งเดียว อย่าจ่ายซ้ำ ↓↓↓
        </div>
        <img class="qr-img" :src="imgSrc"/>
        <van-button type="primary" size="small" class="qr-download" v-on:click="onDownload">
            {{ $t('cashier.save_qrcode') }}
        </van-button>
    </div>
</template>

<script>
export default {
    name: "PayQRCode",
    props: {
        imgSrc: {type: String, required: true},
        downloadUrl: {type: String, default: ""},
        accountType: {type: Number, default: 0}
    },
    methods: {
        onDownload() {
            if (!this.downloadUrl) return;
            const a = document.createElement("a");
            a.href = this.downloadUrl;
            a.click();
        }
    }
};
</script>

<style lang="less" scoped>
.qr-block {
    text-align: center;
    margin: 20px auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    .scan-type-tip {
        font-size: 15px;
        font-weight: 700;
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 10px;
        display: inline-block;
        border: 1px solid transparent;

        &.is-wechat {
            color: #34a853;
            background: #e6f9ec;
            border-color: #b7e5c1;
        }

        &.is-alipay {
            color: #1f6ef2;
            background: #eef3ff;
            border-color: #cfd8ff;
        }
    }

    .qr-text {
        font-size: 14px;
        color: #d97706;
        font-weight: 700;
        margin-bottom: 10px;
        line-height: 1.6;
    }

    .qr-img {
        width: 80%;
        border-radius: 12px;
        border: 2px solid #e6ecff;
        object-fit: contain;
        margin-bottom: 12px;
    }

    .qr-download {
        background: #1f6ef2;
        color: #fff;
        border-radius: 8px;
        font-weight: 600;
        height: 38px;
        padding: 0 20px;
        margin-bottom: 12px;
    }

    .transfer-tip {
        margin-top: 8px;
        margin-bottom: 6px;
        color: #e53935;
        font-size: 13px;
        font-weight: 600;
        background: #fff2f2;
        border: 1px dashed #f2b5b5;
        border-radius: 8px;
        padding: 6px 10px;
        line-height: 1.5;
    }
}
</style>
