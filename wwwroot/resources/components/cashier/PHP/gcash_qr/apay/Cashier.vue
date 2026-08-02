<template>
    <div class="gcash-page" v-if="!$isEmpty(info)">
        <div class="gcash-shell">
            <div class="hero">
                <img class="brand-logo" src="@/img/php/img.png" alt="QRPh" />
            </div>

            <div class="content-stack">
                <div class="summary-card">
                    <div class="summary-row">
                        <span class="summary-label">Order No.</span>
                        <span class="summary-value">{{ $config.ordernumber || '--' }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Amount Due</span>
                        <span class="summary-value amount">{{ formatAmount(info.amount) }} PHP</span>
                    </div>
                </div>

                <div class="qr-card">
                    <button class="open-btn" type="button" @click="onOpenApp" v-if="info.collection_app_link && $isPhone()">
                        OPEN in External App
                    </button>

                    <div class="qr-box">
                        <img
                            v-if="info.collection_qrcode"
                            class="qr-image"
                            :src="info.collection_qrcode"
                            alt="GCash QR"
                        />
                        <div v-else class="qr-placeholder">QR</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
export default {
    name: 'Cashier',
    emits: ['submit'],
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        }
    },
    methods: {
        action(obj){
            this.$emit('submit',obj);
        },
        onOpenApp() {
            if (!this.info.collection_app_link) return;
            window.location.href = this.info.collection_app_link;
        },
        formatAmount(val) {
            if (val === null || val === undefined || val === '') return '';

            let s = String(val).trim().replace(/,/g, '');
            if (s === '') return '';
            if (!/^-?\d+(\.\d+)?$/.test(s)) return String(val);

            const sign = s.startsWith('-') ? '-' : '';
            if (sign) s = s.slice(1);

            const [intPart, decPart] = s.split('.');
            const intWithComma = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return sign + (decPart !== undefined ? `${intWithComma}.${decPart}` : intWithComma);
        }
    }
}
</script>

<style lang="less" scoped>
.gcash-page {
    min-height: 100vh;
    background: #edf2fb;
    padding: 0 0 40px;
    box-sizing: border-box;
}

.gcash-shell {
    width: 100%;
    max-width: 750px;
    margin: 0 auto;
    position: relative;
}

.hero {
    height: 232px;
    width: 100%;
    border-radius: 0;
    background: linear-gradient(180deg, #1659d8 0%, #0451dd 100%);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 34px;
    box-sizing: border-box;
}

.brand-logo {
    width: 228px;
    height: auto;
    display: block;
}

.content-stack {
    position: relative;
    z-index: 1;
    margin: -88px auto 0;
    width: calc(100% - 68px);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(24, 57, 125, 0.10);
    overflow: hidden;
}

.summary-card {
    background: #fff7fb;
    padding: 24px 22px 22px;
    box-sizing: border-box;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.summary-label {
    font-size: 16px;
    color: #9ca3af;
}

.summary-value {
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    text-align: right;
    word-break: break-word;
}

.summary-value.amount {
    color: #1d4ed8;
}

.qr-card {
    background: #fff;
    border-radius: 16px 16px 0 0;
    padding: 24px 20px 24px;
    box-sizing: border-box;
    margin-top: -2px;
    position: relative;
    z-index: 1;
}

.open-btn {
    width: 100%;
    height: 50px;
    border: none;
    border-radius: 8px;
    background: #145dde;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.2px;
    cursor: pointer;
}

.qr-box {
    width: 244px;
    height: 244px;
    margin: 28px auto 0;
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
    border: 2px dashed #dbe3f2;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 34px;
    font-weight: 700;
}

@media (max-width: 420px) {
    .gcash-page {
        padding: 0 10px 26px;
    }

    .hero {
        height: 210px;
        padding-top: 28px;
    }

    .brand-logo {
        width: 196px;
    }

    .content-stack {
        width: calc(100% - 28px);
        margin-top: -74px;
    }

    .summary-card {
        padding: 20px 16px 18px;
    }

    .summary-row {
        margin-bottom: 14px;
    }

    .summary-label,
    .summary-value {
        font-size: 15px;
    }

    .qr-card {
        padding: 22px 14px 18px;
    }

    .qr-box {
        width: 220px;
        height: 220px;
    }
}
</style>
