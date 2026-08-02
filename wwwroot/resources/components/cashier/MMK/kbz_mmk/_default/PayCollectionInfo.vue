<template>
    <div>
        <section class="section" v-if="info.collection_name">
            <div class="highlight-box">
                <div class="highlight-box__title">ငွေလက်ခံသူ</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{ info.collection_name }}</span>
                    <pay-copy :text="info.collection_name"/>
                </div>
            </div>
            <div class="highlight-box">
                <div class="highlight-box__title">အကောင့်</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{ info.collection_card_no }}</span>
                    <pay-copy :text="info.collection_card_no"/>
                </div>
            </div>
            <div class="highlight-box">
                <div class="highlight-box__title">ဖုန်းနံပါတ်</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{ info.collection_card_no }}</span>
                    <pay-copy :text="info.collection_card_no"/>
                </div>
            </div>
            <div class="utr-group">
                <input
                    class="utr-input"
                    v-model="fiveFigureOrder"
                    placeholder="အော်ဒါနံပါတ်နောက်ဆုံးဂဏာန်း 5လုံး"
                    maxlength="5"
                >
            </div>
            <button class="utr-submit" type="button" @click="submitUtr">တင်ပြရန်</button>
        </section>
    </div>
</template>

<script>
import {Dialog, Toast} from "vant";

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
    data() {
        return {
            fiveFigureOrder: ""
        }
    },
    watch: {
        fiveFigureOrder(newVal, oldVal){
            this.fiveFigureOrder = newVal.replace(/\D/g, '').slice(0, 5);
        }
    },
    methods: {
        submitUtr() {
            if (this.fiveFigureOrder == "") {
                Toast("အော်ဒါနံပါတ်၏ နောက်ဆုံး ၅ စာရိုက်ပါ");
                return;
            }
            this.$ajax.post("cashier/confirmPay", {
                ordernumber: this.$config.ordernumber,
                fiveFigureOrder: this.fiveFigureOrder,
                locale: this.$config.locale
            }).then((res) => {
                if (res.code == 200) {
                    if (res.data.return_url) {
                        Dialog.confirm({
                            title: this.$t("cashier.confim_title"),
                            confirmButtonText: this.$t("cashier.confirm_redirect_merchant"),
                            showCancelButton: false,
                            message: this.$t("cashier.confirm_success"),
                        }).then(() => {
                            window.location.replace(res.data.return_url);
                        }).catch(error => {
                            this.$emit("finish", {step: 3, msg: res.message});
                        });
                    } else {
                        this.$emit("finish", {step: 3, msg: res.message});
                    }
                } else {
                    this.$emit("finish", {step: 4, msg: res.message});
                }
            }).catch((error) => {
                this.$emit("finish", {step: 4, msg: error});
            });
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

/* UTR 输入与提交 */
.utr-group {
    margin-top: 12px;
}

.utr-label {
    display: block;
    margin-bottom: 6px;
    color: #374151;
    font-size: 13px;
    font-weight: 600;
}

.utr-input {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box; /* 防止 100% + padding/border 造成溢出 */
    display: block;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 14px;
    background: #f3f4f6;
    font-size: 16px;
    letter-spacing: 2px;
    text-align: center;
    color: #111827;
    outline: none;
    -webkit-appearance: none; /* iOS Safari 去默认样式 */
    appearance: none;
}

.utr-input::placeholder {
    color: #9ca3af;
    letter-spacing: 1.6px;
}

.utr-input:focus {
    border-color: #2b5cff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    background: #fff;
}

.utr-submit {
    margin-top: 12px;
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 12px 16px;
    background: #5f7193;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
}
</style>
