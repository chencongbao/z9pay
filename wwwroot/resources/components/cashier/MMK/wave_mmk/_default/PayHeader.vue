<!-- UpiHeader.vue -->
<template>
    <header class="bank-bar">
        <div class="bank-bar__inner">
            <div class="bank-logo-wrap">
                <img src="@/img/mmk_logo1.png" class="logo-img"/>
            </div>
            <span class="bank-name">WAVEPAY</span>
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
    background: #ffffff;
}

/* Vant 图标（白色） */
.logo-img {
    width: 50px;
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

    .logo-img {
        width: 50px;
    }
}
</style>
