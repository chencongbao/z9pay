<template>
    <div class="vietqr-page">
        <div class="vietqr-shell">
            <div class="vietqr-card">
                <header class="brand-header">
                    <img class="brand-logo" src="@/img/vn_h5/10001.png" alt="VietQR" />
                </header>

                <div class="qr-wrap">
                    <img
                        v-if="info.collection_qrcode"
                        class="qr-image"
                        :src="info.collection_qrcode"
                        alt="VietQR"
                    />
                    <div v-else class="qr-placeholder">QR</div>
                </div>

                <div class="brand-footer">
                    <img class="footer-logo" src="@/img/vn_h5/10003.png" alt="napas BIDV" />
                </div>

                <div class="account-info">
                    <div class="info-line">
                        <span class="info-label">Tên chủ TK:</span>
                        <span
                            class="info-value"
                            v-clipboard:copy="info.collection_name"
                            v-clipboard:success="onCopy"
                            v-clipboard:error="onError"
                        >
                            {{ info.collection_name || '--' }}
                            <span class="copy-mark">⧉</span>
                        </span>
                    </div>

                    <div class="info-line">
                        <span class="info-label">Số TK:</span>
                        <span
                            class="info-value"
                            v-clipboard:copy="info.collection_card_no"
                            v-clipboard:success="onCopy"
                            v-clipboard:error="onError"
                        >
                            {{ info.collection_card_no || '--' }}
                            <span class="copy-mark">⧉</span>
                        </span>
                    </div>

                    <div class="info-line bank-line">
                        <span
                            class="info-value"
                            v-clipboard:copy="info.collection_bank_name"
                            v-clipboard:success="onCopy"
                            v-clipboard:error="onError"
                        >
                            {{ info.collection_bank_name || '--' }}
                            <span class="copy-mark">⧉</span>
                        </span>
                    </div>

                    <div class="info-line amount-line">
                        <span class="info-label">Số tiền:</span>
                        <span
                            class="info-value amount-value"
                            v-clipboard:copy="info.amount"
                            v-clipboard:success="onCopy"
                            v-clipboard:error="onError"
                        >
                            {{ formatMoneyComma(info.amount) }}
                            <span class="copy-mark">⧉</span>
                        </span>
                    </div>
                </div>

                <button
                    v-if="info.download_url"
                    class="download-btn"
                    type="button"
                    @click="onDownload"
                >
                    Lấy đường link
                    <span class="copy-mark">⧉</span>
                </button>

                <div class="meta-box">
                    <div class="countdown">
                        <span class="meta-label">Thời gian còn lại:</span>
                        <van-count-down :time="countdownTime" @finish="action">
                            <template #default="timeData">
                                <span class="countdown-value">{{ padTime(timeData.hours) }}:{{ padTime(timeData.minutes) }}:{{ padTime(timeData.seconds) }}</span>
                            </template>
                        </van-count-down>
                    </div>
                    <div class="reference-code">Ref: {{ $config.ordernumber }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Toast } from "vant";

export default {
    name: "Cashier",
    emits: ["submit"],
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        },
    },
    computed: {
        countdownTime() {
            const seconds = Number(this.info.time || 0);
            return seconds > 0 ? seconds * 1000 : 0;
        },
    },
    methods: {
        action(obj) {
            this.$emit("submit", obj);
        },
        onCopy() {
            Toast(this.$t("cashier.copy_success"));
        },
        onError() {
            Toast(this.$t("cashier.copy_fail"));
        },
        onDownload() {
            if (!this.info.download_url) return;
            const a = document.createElement("a");
            a.href = this.info.download_url;
            a.click();
        },
        padTime(val) {
            return String(val || 0).padStart(2, "0");
        },
        formatMoneyComma(val) {
            if (val === null || val === undefined || val === "") return "";

            let s = String(val).trim().replace(/,/g, "");
            if (s === "") return "";
            if (!/^-?\d+(\.\d+)?$/.test(s)) return String(val);

            const sign = s.startsWith("-") ? "-" : "";
            if (sign) s = s.slice(1);

            const [intPart, decPart] = s.split(".");
            const intWithComma = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            return sign + (decPart !== undefined ? `${intWithComma}.${decPart}` : intWithComma);
        },
    },
};
</script>

<style scoped>
.vietqr-page {
    background: #f5f6f8;
    box-sizing: border-box;
    color: #0f172a;
}

.vietqr-shell {
    margin: 0 auto;
}

.vietqr-card {
    background: #fff;
    border-radius: 0;
    box-shadow: none;
    padding: 0 0 18px;
    text-align: center;
}

.brand-header {
    margin-bottom: 0;
}

.brand-logo {
    width: 100%;
    max-width: 310px;
    height: auto;
    display: block;
    margin: 0 auto;
}

.qr-wrap {
    width: 310px;
    height: 350px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid #222;
}

.qr-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.qr-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 28px;
    font-weight: 700;
}

.brand-footer {
    margin: 0 auto 10px;
}

.footer-logo {
    width: 100%;
    max-width: 260px;
    height: auto;
    display: block;
    margin: 0 auto;
}

.account-info {
    color: #1f8aa5;
    font-size: 15px;
}

.info-line {
    margin-top: 8px;
    line-height: 1.5;
}

.bank-line {
    margin-top: 4px;
}

.amount-line {
    margin-top: 2px;
}

.info-label {
    color: #3c6670;
}

.info-value {
    color: #2a8ba1;
    word-break: break-word;
}

.amount-value {
    color: #1f8aa5;
    font-weight: 500;
}

.copy-mark {
    margin-left: 4px;
    font-size: 12px;
    color: #8bb7c1;
}

.download-btn {
    margin: 18px auto 0;
    min-width: 170px;
    height: 38px;
    padding: 0 18px;
    border: 1px solid #1f8aa5;
    border-radius: 8px;
    background: #fff;
    color: #246d7f;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.meta-box {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px dashed #d7e4e8;
    color: #64748b;
    font-size: 12px;
}

.countdown {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.meta-label {
    color: #64748b;
}

.countdown-value {
    color: #e11d48;
    font-weight: 700;
}

.reference-code {
    margin-top: 6px;
    word-break: break-all;
}

@media (max-width: 960px) {
    .qr-wrap {
        width: min(310px, calc(100vw - 56px));
        height: min(350px, calc(100vw - 16px));
    }
}
</style>
