<!-- UpiHeader.vue -->
<template>
    <header class="upi-bar">
        <div class="upi-bar__inner">
            <div class="upi-badge">UPI</div>
            <div class="upi-meta">
                <div class="upi-title">
                    <span class="upi-title-text">Payment</span>
                </div>
                <div class="upi-subtitle">UNIFIED PAYMENTS INTERFACE</div>
            </div>
        </div>
    </header>
</template>

<script>
export default {name: "PayHeader"};
</script>

<style scoped>
/* 顶部条 */
.upi-bar {
    color: #fff;
    position: relative;
    overflow: hidden;
}

/* 细微高光质感 */
.upi-bar::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(120% 80% at 70% -30%, rgba(255, 255, 255, .12), transparent 60%),
    radial-gradient(120% 90% at -10% 120%, rgba(255, 255, 255, .08), transparent 60%);
    pointer-events: none;
}

.upi-bar__inner {
    max-width: 980px; /* 可按需删掉或调整 */
    margin: 0 auto;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 18px;
    gap: 14px;
}

/* 左侧 UPI 白底方块 */
.upi-badge {
    background: #fff;
    color: #1f3b73;
    font-weight: 800;
    font-size: 18px;
   width: 40px;
    height: 40px;
    border-radius: 4px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* 右侧标题与副标题 */
.upi-meta {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.upi-title {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.upi-title-text {
    font-size: 24px;
    font-weight: 800;
    text-shadow: 0 1px 1px rgba(0, 0, 0, .18);
}

/* 小字加粗更清晰 */
.upi-subtitle {
    margin-top: 4px;
    font-size: 11px;
    font-weight: 700; /* 按要求加粗 */
    letter-spacing: 1.2px; /* 拉字距提升可读性 */
    opacity: .95;
    text-transform: uppercase;
    text-shadow: 0 1px 1px rgba(0, 0, 0, .18);
    white-space: nowrap;
}

/* 小屏适配 */
@media (max-width: 420px) {
    .upi-bar {
        height: 58px;
    }

    .upi-title-text {
        font-size: 24px;
    }

    .upi-badge {
        background: #fff;
        color: #1f3b73;
        font-weight: 800;
        font-size: 16px;
        width: 40px;
        height: 40px;
        border-radius: 4px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .upi-subtitle {
        font-size: 10px;
        letter-spacing: 1px;
    }
}
</style>
