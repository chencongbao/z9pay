<template>
    <div class="pay-wrapper">
        <!-- 顶部栏 -->
        <PayHeader :logo="logo" :name="name" />

        <!-- 主体卡片 -->
        <main class="card">
            <slot />
        </main>
    </div>
</template>

<script>
export default {
    name: "PayWrapper",
    props: {
        name: { type: String, default: "" },
        logo: { type: String, default: "" },
    }
};
</script>

<style lang="less" scoped>
.pay-wrapper {
    min-height: 100vh;
    background: #1f6ef2;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 12px;
    margin: 0 auto;

    .card {
        width: 100%;
        max-width: 760px;
        background: #fff;
        border-radius: 10px;
        padding: 16px;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        box-sizing: border-box;
        margin-top: 16px;
    }
}
@media (min-width: 769px) {
    .pay-wrapper {
        max-width: 750px;
    }
}
</style>
