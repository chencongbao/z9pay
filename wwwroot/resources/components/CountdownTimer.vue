<template>
    <div class="countdown-section" v-if="remain >= 0">
        <div class="divider"></div>
        <!-- 倒计时 -->
        <div class="countdown-wrap">
            <div class="countdown">{{ text }}</div>
        </div>
        <!-- 固定提示文字 -->
        <div class="tips">
            {{$t('cashier.timer_tip1',{time:minite})}}<br/>
            {{$t('cashier.timer_tip2')}}<br/>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CountdownTimer',
    props: {
        seconds: {type: Number, default: 0},
        minite: {type: Number, default: 0},
    },
    emits: ['finish'],
    data() {
        return {
            remain: this.seconds,
            timer: null
        };
    },
    computed: {
        text() {
            const s = Math.max(0, this.remain);
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return `${m.toString().padStart(2, '0')} : ${sec.toString().padStart(2, '0')}`;
        },
    },
    watch: {
        seconds(n) {
            this.remain = n;
            this.restart();
        },
    },
    mounted() {
        this.start();
    },
    beforeUnmount() {
        this.stop();
    },
    methods: {
        start() {
            if (this.timer || this.remain <= 0) {
                this.$emit('finish', {step: 4, msg: this.$t('cashier.pay_overtime')});
                return;
            }
            this.timer = setInterval(() => {
                if (this.remain > 0) {
                    this.remain -= 1;
                } else {
                    this.stop();
                    this.$emit('finish', {step: 4, msg: this.$t('cashier.pay_overtime')});
                }
            }, 1000);
        },
        stop() {
            if (!this.timer) return;
            clearInterval(this.timer);
            this.timer = null;
        },
        restart() {
            this.stop();
            this.start();
        },
        reset(to = this.seconds) {
            this.remain = to;
        },
    },
};
</script>

<style lang="less" scoped>

.divider {
    height: 1px;
    background: #e9eef5;
    margin: 12px 0 8px;
}

.countdown-wrap {
    display: flex;
    justify-content: center;
    margin-top: 6px;
}

.countdown {
    background: #1f6ef2;
    color: #fff;
    border-radius: 20px;
    padding: 6px 12px;
    font-weight: 700;
    min-width: 88px;
    text-align: center;
}

/* 固定提示样式 */
.tips {
    margin: 14px 0 10px;
    text-align: center;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}
</style>
