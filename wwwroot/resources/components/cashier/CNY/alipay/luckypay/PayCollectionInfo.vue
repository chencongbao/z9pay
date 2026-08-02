<template>
    <div>
        <section class="section" v-if="info.collection_card_no">
            <div class="highlight-box">
                <div class="highlight-box__title">{{$t('cashier.collection_card_no')}}</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{info.collection_card_no}}</span>
                    <pay-copy :text="info.collection_card_no"/>
                </div>
                <div class="highlight-box__hint">⚠️ 请使用支付宝[向手机号转账]功能，输入收款账号</div>
            </div>

            <div class="highlight-box">
                <div class="highlight-box__title">{{$t('cashier.collection_name')}}</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{info.collection_name}}</span>
                    <pay-copy :text="info.collection_name"/>
                </div>
                <div class="highlight-box__hint">⚠️ {{$t('cashier.collection_name_tip')}}</div>
            </div>
            <div class="highlight-box">
                <div class="highlight-box__title">{{$t('cashier.collection_bank_name')}}</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{info.collection_bank_name}}</span>
                    <pay-copy :text="info.collection_bank_name"/>
                </div>
            </div>
            <div class="highlight-box" v-if="info.collection_bank_branch">
                <div class="highlight-box__title">{{$t('cashier.collection_bank_branch')}}</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{info.collection_bank_branch}}</span>
                    <pay-copy :text="info.collection_bank_branch"/>
                </div>
            </div>
        </section>
        <section class="section" v-else>
            <PayQRCode :imgSrc="info.collection_qrcode" :downloadUrl="info.download_url" :accountType="info.account_type"/>
        </section>
        <open-app-button :info="info" />
        <section class="section" v-if="info.collection_qrcode && info.collection_card_no">
            <div class="method-list">
                <div class="method-item">
                    <div class="method-header" v-on:click="clickShowQrCode">
                        <div class="method-info">
                            <div class="method-icon">
                                <van-icon name="scan"/>
                            </div>
                            <div class="method-name">{{$t('cashier.pay_qrcode_title')}}</div>
                        </div>
                        <button class="toggle-btn" >
                            {{showQrcode ? $t('cashier.pay_qrcode_hidden')  : $t('cashier.pay_qrcode_show')}}
                        </button>
                    </div>

                    <transition name="fade" v-if="showQrcode">
                        <PayQRCode :imgSrc="info.collection_qrcode" :downloadUrl="info.download_url" :accountType="info.account_type"/>
                    </transition>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: "PayCollectionInfo",
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        },
    },
    data(){
        return {
            showQrcode: false,
        }
    },
    methods: {
        clickShowQrCode(){
            this.showQrcode = !this.showQrcode;
        }
    }
}
</script>

<style lang="less" scoped>
/* 说明文字 */
.tips {
    margin: 14px 0 10px;
    text-align: center;
    color: #6b7280;
    font-size: 13px;
}

.method-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 16px;
}

.method-item {
    border: 1px solid #e6ebff;
    border-radius: 10px;
    padding: 10px;
    background: #f9fbff;
}

.method-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.method-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.method-icon {
    font-size: 20px;
}

.method-name {
    font-weight: 700;
    color: #1f6ef2;
}

.toggle-btn {
    background: transparent;
    color: #1f6ef2;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
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

/* ===== 小屏优化 ===== */
@media (max-width: 400px) {
    .highlight-box__content {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .highlight-box__value {
        font-size: 18px;
    }

    .btn-copy {
        width: 100%;
        text-align: center;
    }
}
.highlight-box {
    border: 1px solid #dce4f9;
    background: #f9fbff;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 12px;
    transition: all 0.2s ease-in-out;

    &:hover {
        border-color: #b9c7f9;
        box-shadow: 0 2px 6px rgba(31, 110, 242, 0.08);
    }
}

.highlight-box__title {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 6px;
    font-weight: 600;
}

.highlight-box__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.highlight-box__value {
    font-size: 20px;
    font-weight: 800;
    color: #1f2937;
    letter-spacing: 0.5px;
    word-break: break-all;
    font-family: 'SF Mono', Consolas, 'Courier New', monospace;
    user-select: text;
}

.highlight-box__hint {
    margin-top: 8px;
    font-size: 13px;
    color: #d97706;
    background: #fff8e6;
    border: 1px dashed #ffe3b3;
    border-radius: 8px;
    padding: 6px 8px;
    line-height: 1.5;
}
</style>
