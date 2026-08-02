<template>
    <div>
        <section class="section" v-if="info.collection_card_no">
            <div class="highlight-box">
                <div class="highlight-box__title">UPI</div>
                <div class="highlight-box__content">
                    <span class="highlight-box__value">{{ info.collection_card_no }}</span>
                    <pay-copy :text="info.collection_card_no"/>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="method-title">Choose a payment method to pay</div>
            <div class="method-list">
                <button type="button"
                        class="method-item"
                        :class="{ 'is-active': selectedMethod === 'paytmmp' }"
                        @click="selectMethod('paytmmp')">
                    <div class="method-header">
                        <div class="method-info">
                            <div class="method-icon" :style="{ background: '#0ea5e9', color: '#fff' }">P</div>
                            <div class="method-name">Paytm</div>
                        </div>
                        <div class="method-arrow">›</div>
                    </div>
                </button>

                <button type="button"
                        class="method-item"
                        :class="{ 'is-active': selectedMethod === 'phonepe' }"
                        @click="selectMethod('phonepe')">
                    <div class="method-header">
                        <div class="method-info">
                            <div class="method-icon" :style="{ background: '#7c3aed', color: '#fff' }">पे</div>
                            <div class="method-name">PhonePe</div>
                        </div>
                        <div class="method-arrow">›</div>
                    </div>
                </button>
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
    data() {
        return {
            selectedMethod: false,
        }
    },
    methods: {
        selectMethod(id) {
            this.selectedMethod = id;
            let scheme = id === 'phonepe' ? 'phonepe://' : 'paytmmp://';
            let appInfo = this.info.collection_app_info;
            if (typeof appInfo === 'string' && appInfo.trim() !== '') {
                try {
                    appInfo = JSON.parse(appInfo);
                } catch (e) {
                    console.error('collection_app_info JSON 解析失败:', e, appInfo);
                    appInfo = null;
                }
            }
            if (appInfo && typeof appInfo === 'object' && appInfo[id]) {
                scheme = appInfo[id];
            }
            this.launchDeepLink(scheme);
        },
        launchDeepLink(scheme) {
            const ua = navigator.userAgent || '';
            const isIOS = /iP(hone|od|ad)/.test(ua);
            const isAndroid = /Android/i.test(ua);

            try {
                if (isIOS) {
                    window.location.href = scheme;
                    return;
                }
                if (isAndroid) {
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = scheme;
                    document.body.appendChild(iframe);
                    setTimeout(() => {
                        try {
                            document.body.removeChild(iframe);
                        } catch (e) {
                        }
                    }, 1800);
                    return;
                }
                // 其它环境兜底
                window.location.href = scheme;
            } catch (e) {
                // 静默失败即可
                console && console.warn && console.warn('open app failed', e);
            }
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

/* 高亮信息盒 */
.highlight-box {
    border: 1px solid #dce4f9;
    background: #f9fbff;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 12px;
    transition: all 0.2s ease-in-out;
}

.highlight-box:hover {
    border-color: #b9c7f9;
    box-shadow: 0 2px 6px rgba(31, 110, 242, 0.08);
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
    letter-spacing: .5px;
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

/* 支付方式 */
.method-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin: 6px 0 8px;
}

.method-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 16px;
}

.method-item {
    width: 100%;
    box-sizing: border-box;
    text-align: left;
    display: block;
    border: 1px solid #e6ebff;
    border-radius: 10px;
    padding: 10px;
    background: #ffffff;
    transition: all .15s ease;
    overflow: hidden; /* 圆角裁切，避免子元素外溢 */
    -webkit-tap-highlight-color: transparent;
}

.method-item .method-header {
    padding: 4px 2px;
}

.method-item:hover {
    box-shadow: 0 1px 4px rgba(2, 6, 23, .06);
    border-color: #d7e2ff;
}

.method-item:focus-visible {
    outline: 3px solid rgba(37, 99, 235, .25);
    outline-offset: 1px;
}

.method-item.is-active {
    border-color: #2b5cff;
    background: linear-gradient(90deg, #ffffff, #eef4ff);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, .12) inset;
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
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 18px;
    color: #fff;
}

.method-name {
    font-weight: 700;
    color: #1f6ef2;
}

.method-arrow {
    font-size: 20px;
    color: #64748b;
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

.utr-submit:hover {
    filter: brightness(0.98);
}

.utr-submit:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* 小屏优化 */
@media (max-width: 400px) {
    .highlight-box__content {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .highlight-box__value {
        font-size: 18px;
    }

    .utr-input {
        letter-spacing: 1.5px;
    }
}

.utr-submit.enabled {
    background: #2563eb; // 启用后更明显
}
</style>
