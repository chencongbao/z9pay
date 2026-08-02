<template>
    <header class="pay-header">
        <div class="brand">
            <!-- 优先显示 logo -->
            <template v-if="logo">
                <img :src="getLogoSrc(logo)" class="brand__logo" alt="brand logo" />
            </template>

            <!-- 如果没有 logo，再显示 name -->
            <template v-else-if="name">
                <span class="brand__name">{{ name }}</span>
            </template>
        </div>
    </header>
</template>

<script>
export default {
    name: "PayHeader",
    props: {
        logo: { type: String, default: "" },
        name: { type: String, default: "zh_CN" },
    },
    methods: {
        getLogoSrc(filename) {
            // 注意：从当前文件相对路径写到 img 目录
            return new URL(`../img/${filename}`, import.meta.url).href;
        }
    }
}
</script>

<style lang="less" scoped>
.pay-header {
    width: 100%;
    max-width: 760px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;

    .brand {
        flex:1;
        display: flex;
        justify-content: center;
        align-items: center;

        &__logo {
            height: 48px; /* 你可以改为 40px、56px 等 */
            width: auto; /* 自动保持比例 */
            object-fit: contain;
        }

        &__name {
            font-size: 18px;
            font-weight: 700;
        }
    }

    .lang-trigger {
        display: flex;
        align-items: center;
        gap: 4px;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #fff;
        border-radius: 8px;
        padding: 6px 10px;
        cursor: pointer;

        &__text {
            font-size: 14px;
            font-weight: 600;
        }

        &:hover {
            background: rgba(255, 255, 255, 0.25);
            transition: 0.3s;
        }
    }

    .lang-sheet {
        display: flex;
        flex-direction: column;
        height: 100%;

        .lang-tip {
            padding: 12px 16px;
            font-size: 14px;
            color: #444;
            background: #f7f8fa;
            border-bottom: 1px solid #eee;
        }

        .lang-list {
            flex: 1;
            overflow: auto;
            padding: 6px 0;
        }

        .lang-actions {
            margin-top: auto;
            padding: 16px;
            display: grid;
            gap: 10px;
        }

        .theme-blue {
            --van-primary-color: #1f6ef2;
            --van-nav-bar-background: #1f6ef2;
            --van-nav-bar-title-text-color: #fff;
            --van-nav-bar-icon-color: #fff;
        }
    }
}
</style>
