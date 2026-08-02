<template>
    <div class="vnd-card-page">
        <div class="space-bg" aria-hidden="true">
            <div class="stars"></div>
            <div class="earth-glow"></div>
            <div class="earth"></div>
            <div class="city-lights lights-a"></div>
            <div class="city-lights lights-b"></div>
        </div>

        <main class="card-shell">
            <section v-if="showPayWaiting" class="waiting-card">
                <div class="waiting-title">Đang xử lý thanh toán</div>
                <div class="waiting-countdown">{{ countdownText }}</div>
                <p>Vui lòng chờ hệ thống kiểm tra trạng thái đơn hàng.</p>
                <p class="checking">Checking payment status...</p>
            </section>

            <van-form v-else class="topup-form" @submit="onSubmit">
                <p class="form-tip">Vui lòng nhập đúng thông tin tránh trường hợp thẻ bị khóa.</p>

                <section class="form-section">
                    <div class="field-label">LOẠI THẺ</div>
                    <div class="provider-list">
                        <button
                            v-for="provider in providers"
                            :key="provider.value"
                            class="provider-btn"
                            :class="[{ active: bank_name === provider.value }, provider.className]"
                            type="button"
                            @click="bank_name = provider.value"
                        >
                            <span>{{ provider.label }}</span>
                        </button>
                    </div>
                </section>

                <section class="form-section">
                    <div class="field-label">MỆNH GIÁ</div>
                    <div class="amount-option">{{ amountText }}</div>
                </section>

                <section class="form-section compact">
                    <div class="field-label">SERIAL</div>
                    <van-field
                        v-model="serial"
                        name="serial"
                        class="dark-field"
                        placeholder="Vui lòng nhập số serial của thẻ"
                        maxlength="40"
                        :rules="serialRules"
                    />
                </section>

                <section class="form-section compact">
                    <div class="field-label">MÃ THẺ</div>
                    <van-field
                        v-model="card_pin"
                        name="card_pin"
                        class="dark-field"
                        placeholder="Vui lòng nhập mã thẻ"
                        maxlength="40"
                        :rules="cardRules"
                    />
                </section>

                <button class="submit-btn" type="submit" :disabled="submitting">
                    <span v-if="submitting">{{ $t('cashier.submiting') }}</span>
                    <span v-else>NẠP THẺ</span>
                </button>
            </van-form>
        </main>
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
            bank_name: 'Viettel',
            serial: '',
            card_pin: '',
            submitting: false,
            amount: 0,
            displayAmount: '',
            showPayWaiting: false,
            remainSeconds: 0,
            countdownTimer: null,
            pollTimer: null,
            polling: false,
            finished: false,
            providers: [
                {label: 'viettel', value: 'Viettel', className: 'viettel'},
                {label: 'vinaphone', value: 'Vinaphone', className: 'vinaphone'},
                {label: 'mobifone', value: 'Mobifone', className: 'mobifone'},
                {label: 'Zing', value: 'Zing', className: 'zing'},
                {label: 'Garena', value: 'Garena', className: 'garena'},
                {label: 'Vcoin', value: 'Vcoin', className: 'vcoin'},
            ],
            serialRules: [
                {required: true, message: 'Vui lòng nhập số serial của thẻ'},
            ],
            cardRules: [
                {required: true, message: 'Vui lòng nhập mã thẻ'},
            ],
        }
    },
    computed: {
        amountText() {
            return `${this.formatAmount(this.displayAmount)} đ`;
        },
        countdownText() {
            const seconds = Math.max(0, this.remainSeconds);
            const minutes = Math.floor(seconds / 60);
            const remain = seconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(remain).padStart(2, '0')}`;
        },
    },
    watch: {
        info: {
            handler() {
                this.syncDisplayAmount();
            },
            immediate: true,
            deep: true,
        },
    },
    beforeDestroy() {
        this.stopCountdown();
        this.stopPolling();
    },
    methods: {
        syncDisplayAmount() {
            this.displayAmount = this.pickAmount([
                this.info && this.info.amount,
                this.info && this.info.pay_amount,
                this.info && this.info.show_amount,
                this.$config && this.$config.amount,
                this.$config && this.$config.pay_amount,
                this.amount,
            ]);
        },
        onSubmit() {
            this.submitting = true;

            this.$ajax.post("cashier/deposit/payname", {
                ordernumber: this.$config.ordernumber,
                bank_name: this.bank_name,
                card_no: this.serial,
                card_pin: this.card_pin,
                pay_name: `${this.bank_name}:${this.amountText}`,
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
        },
        pickAmount(values) {
            for (const value of values) {
                if (value === null || value === undefined || value === '') continue;
                const numeric = Number(String(value).replace(/,/g, ''));
                if (Number.isFinite(numeric) && numeric > 0) return value;
            }

            return '';
        },
        formatAmount(value) {
            if (value === null || value === undefined || value === '') return '10,000';
            const numeric = Number(String(value).replace(/,/g, ''));
            if (!Number.isFinite(numeric)) return String(value);
            return numeric.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });
        },
    },
}
</script>

<style lang="less" scoped>
.vnd-card-page {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    padding: 14px 24px 40px;
    box-sizing: border-box;
    color: #20252d;
    background: #02050a;
}

.space-bg,
.stars,
.earth-glow,
.earth,
.city-lights {
    position: absolute;
    inset: 0;
}

.space-bg {
    z-index: 0;
    overflow: hidden;
    background:
        radial-gradient(circle at 17% 8%, rgba(255, 255, 255, .4) 0 1px, transparent 1.5px),
        radial-gradient(circle at 78% 12%, rgba(255, 255, 255, .32) 0 1px, transparent 1.5px),
        radial-gradient(circle at 54% 5%, rgba(255, 255, 255, .24) 0 1px, transparent 1.5px),
        linear-gradient(180deg, #000000 0%, #03070d 45%, #06121d 100%);
}

.stars {
    opacity: .8;
    background-image:
        radial-gradient(circle, rgba(255, 255, 255, .75) 0 1px, transparent 1.5px),
        radial-gradient(circle, rgba(255, 255, 255, .36) 0 1px, transparent 1.5px);
    background-size: 134px 98px, 211px 151px;
    background-position: 0 6px, 38px 20px;
}

.earth-glow {
    top: 88px;
    left: 32%;
    right: -18%;
    bottom: auto;
    height: 122px;
    border-radius: 50%;
    background: radial-gradient(ellipse at center, rgba(94, 211, 165, .38), rgba(94, 211, 165, .13) 36%, transparent 64%);
    filter: blur(10px);
}

.earth {
    top: 120px;
    left: -8%;
    right: -8%;
    bottom: -34%;
    border-radius: 50% 50% 0 0 / 32% 32% 0 0;
    background:
        radial-gradient(ellipse at 22% 62%, rgba(255, 193, 84, .85) 0 1px, transparent 2px),
        radial-gradient(ellipse at 28% 58%, rgba(255, 210, 98, .7) 0 1px, transparent 2px),
        radial-gradient(ellipse at 63% 58%, rgba(255, 188, 72, .9) 0 1px, transparent 2px),
        radial-gradient(ellipse at 76% 55%, rgba(255, 209, 103, .8) 0 1px, transparent 2px),
        linear-gradient(12deg, rgba(19, 51, 80, .88), rgba(8, 29, 47, .86) 43%, rgba(15, 40, 66, .92)),
        radial-gradient(ellipse at 54% 45%, rgba(99, 124, 146, .56), rgba(17, 38, 61, .68) 48%, rgba(7, 19, 33, .96) 100%);
    box-shadow: inset 0 30px 60px rgba(20, 85, 105, .34), inset 0 -120px 110px rgba(0, 0, 0, .44);
    transform: rotate(-1deg);
}

.earth:before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background:
        radial-gradient(ellipse at 43% 37%, rgba(206, 218, 224, .5), transparent 23%),
        radial-gradient(ellipse at 73% 27%, rgba(213, 217, 219, .38), transparent 24%),
        radial-gradient(ellipse at 18% 52%, rgba(215, 219, 216, .32), transparent 20%);
    filter: blur(3px);
}

.city-lights {
    top: 260px;
    height: 220px;
    opacity: .86;
    background-image:
        radial-gradient(circle, rgba(255, 210, 98, .9) 0 1px, transparent 1.7px),
        radial-gradient(circle, rgba(255, 174, 52, .75) 0 1px, transparent 1.7px);
    background-size: 22px 14px, 35px 21px;
    background-position: 12px 4px, 2px 7px;
    filter: drop-shadow(0 0 5px rgba(255, 180, 70, .6));
    mask-image: linear-gradient(135deg, transparent 0 18%, #000 24% 58%, transparent 76%);
}

.lights-b {
    top: 335px;
    left: 48%;
    opacity: .72;
    transform: rotate(-8deg);
}

.card-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 482px;
    margin: 0 auto;
}

.topup-form,
.waiting-card {
    width: 100%;
    border-radius: 6px;
    background: rgba(246, 247, 250, .96);
    box-shadow: 0 22px 48px rgba(0, 0, 0, .32);
    box-sizing: border-box;
}

.topup-form {
    padding: 18px 13px 12px;
}

.form-tip {
    margin: 0 0 24px;
    color: #7a7f87;
    font-size: 14px;
    line-height: 1.35;
}

.form-section {
    margin-top: 0;
    margin-bottom: 28px;
}

.form-section.compact {
    margin-bottom: 16px;
}

.field-label {
    margin-bottom: 9px;
    color: #4c535c;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.25;
}

.provider-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 14px;
    padding: 9px 8px 4px;
}

.provider-btn {
    position: relative;
    flex: 0 0 94px;
    width: 94px;
    height: 32px;
    border: 1px solid transparent;
    border-radius: 4px;
    background: transparent;
    cursor: pointer;
    overflow: hidden;
    transition: transform .18s ease, border-color .18s ease, background .18s ease, box-shadow .18s ease;
}

.provider-btn span {
    position: relative;
    z-index: 1;
    display: block;
    line-height: 32px;
    white-space: nowrap;
}

.provider-btn.active {
    border-color: #7280f3;
    background: #ffffff;
    box-shadow: 0 7px 16px rgba(114, 128, 243, .24), 0 0 0 2px rgba(114, 128, 243, .14);
}

.provider-btn.active:before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-top: 20px solid #7280f3;
    border-left: 20px solid transparent;
}

.provider-btn.active:after {
    content: "";
    position: absolute;
    top: 3px;
    right: 3px;
    width: 6px;
    height: 3px;
    border-left: 2px solid #ffffff;
    border-bottom: 2px solid #ffffff;
    transform: rotate(-45deg);
}

.provider-btn:active {
    transform: scale(.96);
}

.viettel span {
    color: #c62230;
    font-size: 17px;
    font-weight: 800;
    font-style: italic;
}

.vinaphone span {
    color: #15a7d7;
    font-size: 17px;
    font-weight: 800;
}

.mobifone span {
    font-size: 0;
    font-weight: 700;
}

.mobifone span:before,
.mobifone span:after {
    font-size: 16px;
}

.mobifone span:before {
    content: "mobi";
    color: #1c7cb3;
}

.mobifone span:after {
    content: "fone";
    color: #d82836;
}

.zing span {
    color: #70bf39;
    font-size: 26px;
    font-weight: 900;
    text-shadow:
        -1px -1px 0 #fff,
        1px -1px 0 #fff,
        -1px 1px 0 #fff,
        1px 1px 0 #fff,
        0 2px 2px rgba(0, 0, 0, .22);
}

.vcoin span {
    color: transparent;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    font-size: 0;
}

.garena span {
    background-image: url('@/img/vnd/10009.jpg');
}

.vcoin span {
    background-image: url('@/img/vnd/10008.jpg');
}

.amount-option {
    width: 142px;
    height: 52px;
    border: 1px solid #b8c4d7;
    border-radius: 4px;
    background: #f5f6f8;
    color: #121820;
    font-size: 14px;
    font-weight: 800;
    line-height: 52px;
    text-align: left;
    padding-left: 13px;
    box-sizing: border-box;
}

.dark-field {
    border-radius: 5px;
    overflow: hidden;
    background: #18212b;
}

.dark-field::v-deep .van-cell {
    background: #18212b;
}

.dark-field::v-deep .van-field__body {
    min-height: 40px;
}

.dark-field::v-deep .van-field__control {
    color: #ffffff;
    font-size: 13px;
}

.dark-field::v-deep .van-field__control::placeholder {
    color: #8d97a4;
    opacity: 1;
}

.submit-btn {
    width: 100%;
    height: 41px;
    border: 0;
    border-radius: 4px;
    background: #7280f3;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.submit-btn:disabled {
    opacity: .72;
    cursor: not-allowed;
}

.waiting-card {
    margin-top: 0;
    padding: 34px 22px;
    text-align: center;
    color: #344054;
}

.waiting-title {
    color: #111827;
    font-size: 20px;
    line-height: 1.35;
    font-weight: 800;
}

.waiting-countdown {
    width: 156px;
    margin: 18px auto;
    border-radius: 8px;
    background: #7280f3;
    color: #ffffff;
    font-size: 34px;
    line-height: 58px;
    font-weight: 800;
}

.waiting-card p {
    margin: 0 0 10px;
    font-size: 15px;
    line-height: 1.45;
}

.checking {
    padding-top: 6px;
    color: #788393;
    font-size: 13px !important;
}

@media (max-width: 640px) {
    .vnd-card-page {
        padding: 14px 12px 32px;
    }

    .card-shell {
        max-width: 390px;
    }

    .topup-form {
        padding: 16px 12px 12px;
    }

    .form-tip {
        margin-bottom: 22px;
    }

    .form-section {
        margin-bottom: 24px;
    }

    .form-section.compact {
        margin-bottom: 14px;
    }

    .provider-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 6px 0 0;
    }

    .provider-btn {
        width: 100%;
        min-width: 0;
        height: 40px;
        border: 1px solid rgba(148, 163, 184, .35);
        background: rgba(255, 255, 255, .48);
        overflow: hidden;
    }

    .provider-btn span {
        line-height: 38px;
    }

    .provider-btn.active {
        border-color: #7280f3;
        background: #ffffff;
        box-shadow: 0 7px 16px rgba(114, 128, 243, .24), 0 0 0 2px rgba(114, 128, 243, .14);
    }

    .viettel span,
    .vinaphone span {
        font-size: 16px;
    }

    .mobifone span:before,
    .mobifone span:after {
        font-size: 16px;
    }

    .zing span {
        font-size: 24px;
    }
}

@media (max-width: 360px) {
    .vnd-card-page {
        padding-left: 10px;
        padding-right: 10px;
    }

    .topup-form {
        padding-left: 10px;
        padding-right: 10px;
    }

    .provider-list {
        gap: 8px;
    }

    .viettel span,
    .vinaphone span,
    .mobifone span:before,
    .mobifone span:after {
        font-size: 15px;
    }

    .zing span {
        font-size: 22px;
    }
}
</style>
