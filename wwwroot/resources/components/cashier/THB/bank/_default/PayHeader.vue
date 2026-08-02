<!-- UpiHeader.vue -->
<template>
    <header class="bank-bar">
        <div class="bank-bar__inner">

            <!-- LOGO -->
            <div class="bank-logo-wrap">
                <van-icon name="card" class="bank-logo-icon" />
            </div>

            <!-- 名称 BANK（白字） -->
            <span class="bank-name">BANK</span>

        </div>
    </header>
</template>

<script>
export default {name: "PayHeader"};
</script>

<style scoped>
/* 头部栏（透明背景） */
.bank-bar {
    background: transparent;
    position: relative;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 25, 40, .08);
}

/* 顶部光效去掉背景，只保留结构（也可以直接删掉 ::after） */
.bank-bar::after {
    content: "";
    position: absolute;
    inset: 0;
    background: none;
    pointer-events: none;
}

/* 内部布局 */
.bank-bar__inner {
    max-width: 980px;
    margin: 0 auto;
    height: 64px;
    display: flex;
    align-items: center;
    padding: 0 18px;
    gap: 12px;
}

/* LOGO 外框（深蓝底） */
.bank-logo-wrap {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    border: 1px solid rgba(31, 59, 115, 0.15);
    background: #1f3b73; /* 深蓝背景让图标更明显 */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Vant 图标（白色） */
.bank-logo-icon {
    font-size: 24px;
    color: #ffffff;
}

/* BANK 名称（白色） */
.bank-name {
    font-size: 24px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: 1.5px;
}

/* 小屏适配 */
@media (max-width: 420px) {
    .bank-bar__inner {
        height: 58px;
        padding: 0 14px;
    }

    .bank-logo-wrap {
        width: 36px;
        height: 36px;
    }

    .bank-name {
        font-size: 24px;
    }

    .bank-logo-icon {
        font-size: 18px;
    }
}
</style>
