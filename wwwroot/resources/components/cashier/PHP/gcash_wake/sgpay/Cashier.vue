<template>
    <div class="gcash-page" v-if="!$isEmpty(info)">
        <div class="gcash-shell">
            <div class="hero">
                <img class="brand-logo" src="@/img/php/img2.svg" alt="GCash" />
            </div>

            <div class="content-stack">
                <div class="qr-card">
                    <div class="pay-title">
                        Securely complete the payment with your GCash app
                    </div>

                    <button class="open-btn" type="button" @click="onOpenApp" v-if="info.collection_app_link">
                        Open in GCash
                    </button>

                    <div class="scan-tip">
                        or Log in to GCash and scan this QR with the QR Scanner.
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
    background: #e5e5e5;
    padding: 0 0 56px;
    box-sizing: border-box;
}

.gcash-shell {
    width: 100%;
    max-width: none;
    margin: 0 auto;
    position: relative;
}

.hero {
    height: 233px;
    width: 100%;
    border-radius: 0;
    background: #095dde;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 43px;
    box-sizing: border-box;
}

.brand-logo {
    width: 166px;
    height: auto;
    display: block;
    filter: brightness(0) invert(1);
}

.content-stack {
    position: relative;
    z-index: 1;
    margin: -138px auto 0;
    width: 480px;
    max-width: calc(100% - 32px);
}

.qr-card {
    background: #fff;
    border-radius: 14px;
    padding: 40px 30px 50px;
    box-sizing: border-box;
    position: relative;
    z-index: 1;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.pay-title {
    text-align: center;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 700;
    color: #26313d;
}

.open-btn {
    width: 100%;
    height: 56px;
    border: none;
    border-radius: 28px;
    background: #0b61e8;
    color: #fff;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0;
    cursor: pointer;
    margin-top: 24px;
    box-shadow: 0 6px 12px rgba(9, 93, 222, 0.28);
}

.scan-tip {
    margin-top: 30px;
    text-align: center;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 14px;
    line-height: 1.45;
    font-weight: 700;
    color: #26313d;
}

.qr-box {
    width: 230px;
    height: 230px;
    margin: 24px auto 0;
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
        padding: 0 0 34px;
    }

    .hero {
        height: 190px;
        padding-top: 36px;
    }

    .brand-logo {
        width: 148px;
    }

    .content-stack {
        width: calc(100% - 32px);
        margin-top: -108px;
    }

    .qr-card {
        padding: 34px 24px 38px;
    }

    .pay-title {
        font-size: 16px;
    }

    .open-btn {
        height: 52px;
        font-size: 16px;
        margin-top: 22px;
    }

    .scan-tip {
        font-size: 14px;
        margin-top: 26px;
    }

    .qr-box {
        width: 220px;
        height: 220px;
    }
}
</style>
