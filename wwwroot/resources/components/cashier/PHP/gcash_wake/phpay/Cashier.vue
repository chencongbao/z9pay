<template>
    <div class="gcash-page" v-if="!$isEmpty(info)">
        <div class="gcash-shell">
            <div class="hero"></div>

            <div class="content-stack">
                <div class="qr-card">
                    <button class="open-btn" type="button" @click="onOpenApp" v-if="info.collection_app_link && $isPhone()">
                        Open in E-wallet App
                    </button>

                    <div class="scan-tip">
                        Scan this QR with the QR Scanner
                    </div>

                    <div class="qr-box">
                        <img
                            v-if="info.collection_qrcode"
                            class="qr-image"
                            :src="info.collection_qrcode"
                            alt="GCash QR"
                        />
                        <div v-else class="qr-placeholder">QR</div>
                    </div>

                    <div class="qr-desc">
                        Screenshot QR code and pay with E-Wallet APP QR-function
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
    height: 100px;
    width: 100%;
    border-radius: 0;
    background: linear-gradient(180deg, #1659d8 0%, #0451dd 100%);
    box-sizing: border-box;
}

.content-stack {
    position: relative;
    z-index: 1;
    margin: -44px auto 0;
    width: calc(100% - 68px);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(24, 57, 125, 0.10);
    overflow: hidden;
}

.qr-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px 20px 24px;
    box-sizing: border-box;
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

.scan-tip {
    margin-top: 22px;
    text-align: center;
    font-size: 16px;
    color: #6b7280;
}

.qr-box {
    width: 244px;
    height: 244px;
    margin: 14px auto 0;
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

.qr-desc {
    margin: 18px auto 0;
    max-width: 320px;
    text-align: center;
    font-size: 15px;
    line-height: 1.45;
    font-weight: 700;
    color: #111827;
}

@media (max-width: 420px) {
    .gcash-page {
        padding: 0 10px 26px;
    }

    .hero {
        height: 150px;
    }

    .content-stack {
        width: calc(100% - 28px);
        margin-top: -36px;
    }

    .qr-card {
        padding: 22px 14px 18px;
    }

    .scan-tip {
        font-size: 15px;
        margin-top: 18px;
    }

    .qr-box {
        width: 220px;
        height: 220px;
    }

    .qr-desc {
        font-size: 14px;
        margin-top: 16px;
    }
}
</style>
