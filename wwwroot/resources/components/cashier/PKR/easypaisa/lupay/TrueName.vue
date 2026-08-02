<template>
    <div class="pkr-page">
        <div class="pkr-shell">
            <header class="order-head">
                <div class="amount">PKR {{ info.amount || '' }}</div>
                <div class="order-label">Order Number | آرڈر نمبر</div>
                <div class="order-no">{{ $config.ordernumber }}</div>
            </header>

            <div class="divider"></div>

            <section v-if="showPayWaiting" class="waiting-card">
                <div class="waiting-title">Complete Payment | ادائیگی مکمل کریں</div>
                <div class="waiting-countdown">{{ countdownText }}</div>
                <p>Please open Easypaisa App and complete the payment before the countdown ends.</p>
                <p class="urdu">براہ کرم Easypaisa App کھولیں اور الٹی گنتی ختم ہونے سے پہلے ادائیگی مکمل کریں۔</p>
                <p class="checking">Checking payment status...</p>
            </section>

            <van-form v-else class="payment-form" @submit="onSubmit">
                <section class="method-section">
                    <div class="section-row">
                        <span>Select Payment Method</span>
                        <span class="rtl">ادائیگی کا طریقہ منتخب کریں</span>
                    </div>

                    <button class="method-card" type="button" aria-label="easypaisa">
                        <img class="method-logo" src="@/img/pkr/easypaisa.png" alt="easypaisa" />
                    </button>
                </section>

                <section class="wallet-section">
                    <div class="field-row">
                        <span>Wallet Account</span>
                        <span class="rtl">والٹ اکاونٹ</span>
                    </div>

                    <van-field
                        v-model="card_no"
                        name="card_no"
                        class="wallet-field"
                        placeholder="03XXXXXXXXX"
                        size="large"
                        maxlength="100"
                        :rules="walletRules"
                    />
                </section>

                <button class="submit-btn" type="submit" :disabled="submitting">
                    <span v-if="submitting">{{ $t('cashier.submiting') }}</span>
                    <span v-else>Submit | جمع کرائیں</span>
                </button>
            </van-form>

            <section class="notice-card">
                <p>After clicking "Submit", please open your wallet and enter your password to complete the payment.</p>
                <p class="urdu">"جمع کرائیں" پر کلک کرنے کے بعد، براہ کرم اپنا والٹ کھولیں اور ادائیگی مکمل کرنے کے لیے اپنا پاس ورڈ درج کریں۔</p>
                <p>After clicking submit, if you want to change your account for payment, please wait for one minute before trying again with a new account.</p>
                <p class="urdu">سبمٹ پر کلک کرنے کے بعد، اگر آپ ادائیگی کے لیے اپنا اکاونٹ تبدیل کرنا چاہتے ہیں، تو براہ کرم نئے اکاونٹ کے ساتھ دوبارہ کوشش کرنے سے پہلے ایک منٹ انتظار کریں۔</p>
            </section>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TrueName',
    props: {
        info: {type: Object, default: () => ({})},
    },
    data() {
        return {
            card_no: "",
            submitting: false,
            amount: "",
            showPayWaiting: false,
            remainSeconds: 0,
            countdownTimer: null,
            pollTimer: null,
            polling: false,
            finished: false,
            walletRules: [
                { required: true, message: 'Please enter wallet account' },
                { pattern: /^03\d{9}$/, message: 'Must be 11 digits, starting without country code 92' },
            ],
        }
    },
    computed: {
        countdownText() {
            const seconds = Math.max(0, this.remainSeconds);
            const minutes = Math.floor(seconds / 60);
            const remain = seconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(remain).padStart(2, '0')}`;
        },
    },
    beforeDestroy() {
        this.stopCountdown();
        this.stopPolling();
    },
    mounted() {
        if (Number(this.info && this.info.status) === 3) {
            this.startPayWaiting(this.info);
        }
    },
    methods: {
        onSubmit() {
            this.submitting = true;
            this.$ajax.post("cashier/deposit/payname", {
                ordernumber: this.$config.ordernumber,
                card_no: this.card_no,
                pay_name: this.card_no,
                locale: this.$config.locale
            }).then((res) => {
                if (res.code == 200) {
                    this.startPayWaiting(res.data || {});
                } else {
                    this.submitting = false;
                    this.$emit("submit", {step: 1, msg: res.message});
                }
            }).catch(error => {
                this.submitting = false;
                this.$emit("submit", {step: 1, msg: error});
            });
        },
        startPayWaiting(data) {
            const hasTime = data.time !== undefined && data.time !== null && data.time !== '';
            const seconds = hasTime ? Number(data.time) : 60;

            if (hasTime && Number.isFinite(seconds) && seconds <= 0) {
                this.finishAsFailed(this.$t('cashier.pay_overtime'));
                return;
            }

            this.remainSeconds = Number.isFinite(seconds) ? Math.ceil(seconds) : 60;
            this.finished = false;
            this.showPayWaiting = true;
            this.startCountdown();
            this.checkOrderStatus();
            this.startPolling();
        },
        startCountdown() {
            this.stopCountdown();
            this.countdownTimer = setInterval(() => {
                if (this.remainSeconds > 0) {
                    this.remainSeconds -= 1;
                    return;
                }

                this.finishAsFailed(this.$t('cashier.pay_overtime'));
            }, 1000);
        },
        stopCountdown() {
            if (!this.countdownTimer) return;
            clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        },
        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => {
                this.checkOrderStatus();
            }, 3000);
        },
        stopPolling() {
            if (!this.pollTimer) return;
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        },
        checkOrderStatus() {
            if (this.polling || !this.showPayWaiting || this.finished) return;

            this.polling = true;
            this.$ajax.get("/cashier/deposit/query", {
                params: {ordernumber: this.$config.ordernumber},
                hideLoading: true,
            }).then((res) => {
                if (res.code != 200) {
                    this.finishAsFailed(res.message);
                    return;
                }

                const status = Number(res.data && res.data.status);
                if (status === 5) {
                    this.finishAsSuccess(res.message || this.$t('cashier.success'));
                } else if (status !== 3) {
                    this.finishAsFailed(res.message || this.$t('cashier.order_close'));
                }
            }).catch(error => {
                this.finishAsFailed(error);
            }).then(() => {
                this.polling = false;
            });
        },
        finishAsSuccess(message) {
            if (this.finished) return;
            this.finished = true;
            this.stopCountdown();
            this.stopPolling();
            this.$emit("submit", {step: 3, msg: message});
        },
        finishAsFailed(message) {
            if (this.finished) return;
            this.finished = true;
            this.stopCountdown();
            this.stopPolling();
            this.$emit("submit", {step: 4, msg: message});
        }
    },
}
</script>

<style lang="less" scoped>
.pkr-page {
    min-height: 100vh;
    background: #ffffff;
    color: #111111;
    padding: 10px 24px 24px;
    box-sizing: border-box;
}

.pkr-shell {
    width: 100%;
    max-width: 494px;
    margin: 0 auto;
}

.order-head {
    text-align: center;
    padding: 0 0 22px;
}

.amount {
    font-size: 34px;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: 0;
}

.order-label {
    margin-top: 22px;
    color: #858585;
    font-size: 16px;
    line-height: 1.4;
}

.order-no {
    color: #858585;
    font-size: 16px;
    line-height: 1.4;
    word-break: break-all;
}

.divider {
    height: 1px;
    background: #e4e4e4;
}

.payment-form {
    padding-top: 26px;
}

.waiting-card {
    margin-top: 26px;
    border: 1px solid #d8e8df;
    border-radius: 12px;
    background: #f4faf7;
    padding: 24px 18px;
    text-align: center;
    color: #315343;
    box-sizing: border-box;
}

.waiting-title {
    color: #111111;
    font-size: 20px;
    line-height: 1.35;
    font-weight: 800;
}

.waiting-countdown {
    width: 156px;
    margin: 18px auto;
    border-radius: 10px;
    background: #146b55;
    color: #ffffff;
    font-size: 34px;
    line-height: 58px;
    font-weight: 800;
    letter-spacing: 0;
}

.waiting-card p {
    margin: 0 0 10px;
    font-size: 15px;
    line-height: 1.45;
}

.waiting-card .urdu {
    direction: rtl;
}

.checking {
    padding-top: 6px;
    color: #6d7f75;
    font-size: 13px !important;
}

.section-row,
.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    font-size: 16px;
    font-weight: 700;
    color: #111111;
}

.field-row {
    color: #707070;
    font-size: 14px;
    margin-bottom: 12px;
}

.rtl {
    direction: rtl;
    text-align: right;
}

.method-card {
    width: 246px;
    height: 116px;
    margin-top: 12px;
    border: 1px solid #146b55;
    border-radius: 9px;
    background: #e9f3ef;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px 22px;
    box-sizing: border-box;
}

.method-logo {
    width: 100%;
    max-height: 78px;
    object-fit: contain;
    display: block;
}

.wallet-section {
    margin-top: 30px;
}

.wallet-field {
    border: 1px solid #d8d8d8;
    border-radius: 9px;
    overflow: hidden;
}

.wallet-field::v-deep .van-field__body {
    min-height: 54px;
}

.wallet-field::v-deep .van-field__control {
    text-align: center;
    color: #d84a37;
    font-size: 31px;
    font-weight: 800;
}

.wallet-field::v-deep .van-field__control::placeholder {
    color: #d84a37;
    opacity: 1;
}

.submit-btn {
    width: 100%;
    height: 60px;
    margin-top: 24px;
    border: 0;
    border-radius: 10px;
    background: #6fa17f;
    color: #ffffff;
    font-size: 22px;
    font-weight: 800;
    cursor: pointer;
}

.submit-btn:disabled {
    opacity: 0.72;
    cursor: not-allowed;
}

.notice-card {
    margin-top: 14px;
    background: #f8f8f8;
    border-radius: 18px;
    padding: 20px 17px;
    color: #5d5d5d;
    font-size: 14px;
    line-height: 1.35;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.03);
}

.notice-card p {
    margin: 0 0 14px;
}

.notice-card p:last-child {
    margin-bottom: 0;
}

.notice-card .urdu {
    direction: rtl;
    text-align: right;
}

@media (max-width: 420px) {
    .pkr-page {
        padding: 10px 18px 22px;
    }

    .amount {
        font-size: 31px;
    }

    .method-card {
        width: 246px;
        max-width: 100%;
    }

    .wallet-field::v-deep .van-field__control {
        font-size: 28px;
    }

    .submit-btn {
        height: 58px;
        font-size: 20px;
    }
}
</style>
