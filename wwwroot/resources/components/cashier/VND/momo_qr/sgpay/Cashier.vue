<template>
    <div class="momo-page">
        <div class="momo-shell">
            <header class="brand-header">
                <img class="brand-logo" src="@/img/vnd/momo_logo.svg" alt="MoMo" />
            </header>

            <main class="content-card">
                <section class="pay-layout">
                    <div class="qr-panel">
                        <div class="panel-box">
                            <div class="timer-title">Trang chuyển khoản sẽ đóng sau</div>
                            <div class="timer-value">
                                <van-count-down :time="countdownTime" @finish="action">
                                    <template #default="timeData">
                                        <span>{{ padTime(timeData.hours) }}</span>
                                        <span>:</span>
                                        <span>{{ padTime(timeData.minutes) }}</span>
                                        <span>:</span>
                                        <span>{{ padTime(timeData.seconds) }}</span>
                                    </template>
                                </van-count-down>
                            </div>

                            <div class="transfer-label">Mã tham chiếu:</div>
                            <div class="transfer-code">{{$config.ordernumber}}</div>

                            <div class="qr-wrap">
                                <img
                                    v-if="info.collection_qrcode"
                                    class="qr-image"
                                    :src="info.collection_qrcode"
                                    alt="MoMo QR"
                                />
                                <div v-else class="qr-placeholder">QR</div>
                            </div>

                            <button
                                v-if="info.download_url"
                                class="download-btn"
                                type="button"
                                @click="onDownload"
                            >
                                Tải mã QR
                            </button>
                        </div>
                    </div>

                    <div class="info-panel">
                        <div class="panel-box info-box">
                            <div class="info-head">Vui lòng quét mã QR để chuyển tiền.</div>

                            <div class="info-row">
                                <div class="info-main">
                                    <div class="info-label">Tên ngân hàng:</div>
                                    <div class="info-value bank-name">{{ info.collection_bank_name || '--' }}</div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-main">
                                    <div class="info-label">Số tài khoản:</div>
                                    <div class="info-value">{{ info.collection_card_no || '--' }}</div>
                                </div>
                                <button
                                    class="copy-btn"
                                    type="button"
                                    v-clipboard:copy="info.collection_card_no"
                                    v-clipboard:success="onCopy"
                                    v-clipboard:error="onError"
                                >
                                    Sao chép
                                </button>
                            </div>

                            <div class="info-row">
                                <div class="info-main">
                                    <div class="info-label">Tên tài khoản:</div>
                                    <div class="info-value">{{ info.collection_name || '--' }}</div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-main">
                                    <div class="info-label">Số tiền chuyển:</div>
                                    <div class="info-value amount-text">{{ formatMoneyComma(info.amount) }} VND</div>
                                </div>
                                <button
                                    class="copy-btn"
                                    type="button"
                                    v-clipboard:copy="info.amount"
                                    v-clipboard:success="onCopy"
                                    v-clipboard:error="onError"
                                >
                                    Sao chép
                                </button>
                            </div>

                            <div class="info-row">
                                <div class="info-main">
                                    <div class="info-label">Nội dung chuyển khoản:</div>
                                    <div class="info-value amount-text">{{ info.collection_bank_branch || '--' }}</div>
                                </div>
                                <button
                                    class="copy-btn"
                                    type="button"
                                    v-clipboard:copy="info.collection_bank_branch"
                                    v-clipboard:success="onCopy"
                                    v-clipboard:error="onError"
                                >
                                    Sao chép
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="notice-box">
                    <div class="notice-title">Lưu ý:</div>
                    <ol class="notice-list">
                        <li>Vui lòng nhập chính xác nội dung và số tiền yêu cầu từ hệ thống. Thông tin không chính xác sẽ không thể tự động lên điểm.</li>
                        <li>Nếu không thể sử dụng mã QR Code, vui lòng sao chép tài khoản thanh toán.</li>
                        <li>Khi chuyển khoản không làm mới trình duyệt.</li>
                    </ol>
                </section>
            </main>

            <footer class="security-footer">
                <img class="pci-logo" src="@/img/vnd/10006.svg" alt="PCI DSS" />
                <div class="security-copy">
                    <div class="security-title">PCI DSS certification</div>
                    <div class="security-desc">
                        Đạt chứng nhận bảo mật dữ liệu tài chính có thẩm quyền và tuân thủ các tiêu chuẩn bảo mật quốc tế.
                    </div>
                </div>
            </footer>
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
.momo-page {
    min-height: 100vh;
    background: #f4f6fb;
    padding: 24px 14px 36px;
    box-sizing: border-box;
    color: #1f2937;
}

.momo-shell {
    max-width: 1200px;
    margin: 0 auto;
}

.brand-header {
    display: flex;
    justify-content: center;
    margin-bottom: 10px;
}

.brand-logo {
    width: 86px;
    height: 86px;
    object-fit: contain;
    display: block;
}

.content-card {
    background: #fff;
    border: 1px solid #e3e8f2;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 34px 32px 20px;
}

.pay-layout {
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
}

.panel-box,
.notice-box {
    border: 1px solid #e3e8f2;
    border-radius: 16px;
    background: #fff;
}

.qr-panel {
    align-self: start;
}

.qr-panel .panel-box {
    padding: 22px 24px;
    text-align: center;
}

.timer-title {
    font-size: 14px;
    color: #374151;
}

.timer-value {
    margin-top: 6px;
    color: #ef4444;
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
}

.transfer-label {
    margin-top: 12px;
    font-size: 14px;
    color: #6b7280;
}

.transfer-code {
    margin-top: 4px;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1.5;
    word-break: break-all;
}

.qr-wrap {
    width: 224px;
    height: 224px;
    margin: 22px auto 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
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
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 28px;
    font-weight: 700;
}

.download-btn {
    margin-top: 18px;
    width: 184px;
    height: 38px;
    border: 0;
    border-radius: 4px;
    background: #2473ea;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
}

.info-box {
    padding: 14px 14px 10px;
}

.info-head {
    text-align: center;
    font-size: 14px;
    color: #374151;
    margin-bottom: 8px;
}

.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px dashed #e5e7eb;
}

.info-row:last-child {
    border-bottom: none;
}

.info-main {
    min-width: 0;
    flex: 1;
}

.info-label {
    font-size: 14px;
    color: #4b5563;
    margin-bottom: 6px;
}

.info-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.25;
    word-break: break-word;
}

.bank-name {
    font-size: 22px;
}

.amount-text {
    color: #ff1f1f;
}

.copy-btn {
    flex: 0 0 auto;
    min-width: 106px;
    height: 34px;
    padding: 0 16px;
    border: 1px solid #ffb13c;
    border-radius: 4px;
    background: linear-gradient(180deg, #ffbf4d 0%, #ff9f1c 100%);
    color: #fff;
    font-size: 14px;
    cursor: pointer;
}

.notice-box {
    margin-top: 14px;
    padding: 18px 18px 16px;
}

.notice-title {
    color: #ff1f1f;
    font-size: 18px;
    font-weight: 700;
}

.notice-list {
    margin: 10px 0 0;
    padding-left: 26px;
    color: #374151;
    font-size: 14px;
    line-height: 1.9;
    font-weight: 600;
}

.security-footer {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 10px 0;
}

.pci-logo {
    width: 160px;
    height: auto;
    display: block;
}

.security-title {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}

.security-desc {
    margin-top: 4px;
    font-size: 14px;
    color: #6b7280;
}

@media (max-width: 960px) {
    .content-card {
        padding: 20px 16px 16px;
    }

    .pay-layout {
        grid-template-columns: 1fr;
    }

    .qr-panel .panel-box,
    .info-box {
        padding: 18px 16px;
    }

    .qr-wrap {
        width: 200px;
        height: 200px;
    }

    .info-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .copy-btn {
        min-width: 100%;
    }

    .security-footer {
        flex-direction: column;
        align-items: flex-start;
        padding-left: 0;
        padding-right: 0;
    }
}
</style>
