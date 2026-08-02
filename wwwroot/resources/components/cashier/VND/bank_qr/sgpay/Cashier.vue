<template>
    <div class="pg">
        <!-- 顶栏：左订单号 / 中倒计时 / 右Logo -->
        <div class="top">
            <div class="top-inner">
                <div class="top-left">
                    <span class="t-label">Mã GD:</span>
                    <span class="t-id">{{$config.ordernumber}}</span>
                </div>

                <div class="top-mid">
                    <span class="t-label">Hết hạn sau:</span>
                    <span class="t-time">
                        <van-count-down :time="info.time * 1000" v-on:finish="action">
                            <template #default="timeData">
                                <span class="block">{{ timeData.minutes }}</span>
                                <span class="colon">:</span>
                                <span class="block">{{ timeData.seconds }}</span>
                              </template>
                        </van-count-down>
                    </span>
                </div>

                <div class="top-right">
                    <div class="logo">
                        <img class="logo-img" src="@/img/vnd/10002.png" alt="SGPAY" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 主体 -->
        <div class="container">
            <div class="card">
                <!-- 内层大虚线框 -->
                <div class="dash dash-big">
                    <div class="grid">
                        <!-- 左：QR -->
                        <div class="col col-qr">
                            <div class="h1">Cách 1: Chuyển khoản bằng mã QR</div>
                            <div class="h2">Mở ứng dụng ngân hàng và quét QRCode</div>

                            <div class="brand">
                                <img class="brand-img" src="@/img/vnd/10002.png" alt="SGPAY" />
                            </div>

                            <!-- 二维码：先用占位点阵（你有真实二维码图片再替换） -->
                            <div class="qr">
                                <div class="qr-fake">
                                    <img class="qr-img" :src="info.collection_qrcode" alt="SGPAY" v-if="info.collection_qrcode"/>
                                </div>
                            </div>
                            <van-button color="#16a34a" size="small" class="qr-download" v-on:click="onDownload" v-if="info.download_url">
                                Tải mã QR
                            </van-button>

                            <div class="banks">
                                <img class="bank-img napas-img" src="@/img/vnd/10003.webp" alt="napas 247" />
<!--                                <span class="bar">|</span>-->
<!--                                <img class="bank-img acb-img" src="@/img/vnd/10004.png" alt="ACB" />-->
                            </div>

                            <div class="money">{{formatMoneyComma(info.amount)}} VND</div>
                        </div>

                        <div class="vline"></div>

                        <!-- 右：信息 -->
                        <div class="col col-info">
                            <div class="h1 h1-left">
                                Cách 2 : Chuyển khoản <span class="link">thủ công</span> theo thông tin
                            </div>

                            <div class="tbl">
                                <div class="tr">
                                    <div class="k">Ngân hàng</div>
                                    <div class="v">{{info.collection_bank_name}}</div>
                                    <div class="a"></div>
                                </div>

                                <div class="tr">
                                    <div class="k">Số TK</div>
                                    <div class="v mono">{{info.collection_card_no}}</div>
                                    <div class="a"><button class="copy"  v-clipboard:copy="info.collection_card_no" v-clipboard:success="onCopy" v-clipboard:error="onError">Copy</button></div>
                                </div>

                                <div class="tr">
                                    <div class="k">Chủ TK</div>
                                    <div class="v">{{info.collection_name}}</div>
                                    <div class="a"><button class="copy"  v-clipboard:copy="info.collection_name" v-clipboard:success="onCopy" v-clipboard:error="onError">Copy</button></div>
                                </div>

                                <div class="tr">
                                    <div class="k">Nội dung</div>
                                    <div class="v red mono">{{info.collection_bank_branch}}</div>
                                    <div class="a"><button class="copy" v-clipboard:copy="info.collection_bank_branch" v-clipboard:success="onCopy" v-clipboard:error="onError">Copy</button></div>
                                </div>

                                <div class="tr">
                                    <div class="k">Số tiền</div>
                                    <div class="v red">{{formatMoneyComma(info.amount)}} VND</div>
                                    <div class="a"><button class="copy" v-clipboard:copy="info.amount" v-clipboard:success="onCopy" v-clipboard:error="onError">Copy</button></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 注意事项 -->
                    <div class="note dash dash-note">
                        <div class="note-title">Lưu ý quan trọng:</div>
                        <ol class="note-list">
                            <li>
                                Chuyển khoản cần nhập chính xác nội dung
                                <span class="red b">{{info.collection_bank_branch}}</span>
                                và số tiền
                                <span class="red b">{{formatMoneyComma(info.amount)}}VND</span>.
                                Nếu nhập sai nội dung hoặc số tiền có thể dẫn đến không tự động cập nhật hoặc kẹt tiền.
                            </li>
                            <li>
                                Mã QR chỉ có hiệu lực 1 lần duy nhất, không làm mới trang, không lưu và quét mã cho lần giao dịch tiếp theo để tránh sai sót.
                            </li>
                            <li>Trong trường hợp cần được hỗ trợ, vui lòng liên hệ CSKH 24/7.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- 底部安全区 -->
            <div class="sec">
                <div class="sec-icons">
                    <div class="sec-badge">
                        <img class="sec-img sec1" src="@/img/vnd/10005.webp" alt="secure GlobalSign" />
                    </div>
                    <div class="sec-badge">
                        <img class="sec-img sec2" src="@/img/vnd/10006.svg" alt="PCI DSS" />
                    </div>
                </div>

                <div class="sec-title">An toàn thông tin - Tiêu chuẩn bảo mật tuyệt đối</div>
                <div class="sec-sub">
                    Mọi thông tin thanh toán được mã hóa và bảo mật tuyệt đối theo tiêu chuẩn cao nhất
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {Toast} from "vant";

export default {
    name: 'Cashier',
    emits: ['submit'],
    props: {
        info: {
            type: Object,
            default: function () {
                return {};
            },
        }
    },
    methods: {
        action(obj){
            this.$emit('submit',obj);
        },
        onCopy() {
            Toast(this.$t("cashier.copy_success"));
        },
        onError() {
            Toast(this.$t("cashier.copy_fail"));
        },
        onDownload() {
            if (!this.info.download_url) return;
            const a = document.createElement("a");
            a.href = this.info.download_url;
            a.click();
        },
        formatMoneyComma(val) {
            if (val === null || val === undefined || val === '') return ''

            // 转字符串并去掉已有逗号
            let s = String(val).trim().replace(/,/g, '')
            if (s === '') return ''

            // 只接受合法数字（支持负数 / 小数）
            if (!/^-?\d+(\.\d+)?$/.test(s)) return String(val)

            // 处理负号
            const sign = s.startsWith('-') ? '-' : ''
            if (sign) s = s.slice(1)

            // 拆分整数 / 小数
            const [intPart, decPart] = s.split('.')

            // 整数部分加逗号
            const intWithComma = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')

            // 组合返回
            return sign + (decPart !== undefined ? `${intWithComma}.${decPart}` : intWithComma)
        }
    }
}
</script>

<style scoped>
/* ====== 全局 ====== */
.pg{
    min-height:100vh;
    background:#f6f8fb;
    position:relative;
    overflow-x:hidden;
    color:#111827;
    font-size:12px;
    line-height:1.4;
}

.colon {
    font-size: 18px;
    color: #ee0a24;
}
.block {
    font-size: 18px;
    color: #ee0a24;
}

/* 内容层在背景之上 */
.top,.container{ position:relative; z-index:1; }

/* ====== 顶栏（原站：顶部信息在一行，下面有细线） ====== */
.top{
    padding:10px 12px 0;
}
.top-inner{
    max-width:1200px;
    margin:0 auto;
    display:grid;
    grid-template-columns: 1fr auto 1fr;
    align-items:center;
    gap:12px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(15,23,42,.12);
}

/* 左上角订单号：对齐更稳（不抖动、不换行） */
.top-left{
    justify-self:start;
    display:flex;
    align-items:center;
    gap:2px;
    font-size:16px;
    color:#0f172a;
    white-space:nowrap;
}
.t-label{ font-weight:700; color:#275B84; }
.t-id{
    font-weight:800;
    color:#2563eb;
    letter-spacing:.1px;
}

/* 中间倒计时 */
.top-mid{
    justify-self:center;
    display:flex;
    align-items:center;
    gap:4px;
    font-size:16px;
    color:#275B84;
    white-space:nowrap;
}
.t-time{
    font-size:20px;
    color:#ef4444;
    letter-spacing:.2px;
}

/* 右上角 logo */
.top-right{ justify-self:end; }
.logo{ display:inline-flex; align-items:center; }
.logo-img{
    height:20px; width:auto;
    display:block;
    object-fit:contain;
}

/* ====== 主体容器宽度 ====== */
.container{
    max-width:1200px;
    margin:10px auto 0;
    box-sizing:border-box;
}
@media (min-width:1600px){
    .container{ max-width:1320px; }
    .top-inner{ max-width:1320px; }
}

/* ====== 主卡片 ====== */
.card{
    background:#fff;
    border-radius:8px;
    box-shadow:0 8px 18px rgba(16,24,40,.14);
    border:1px solid rgba(15,23,42,.06);
    padding:14px;
}

/* 虚线框：更接近原站（细一点、浅一点） */
.dash{
    border:1px dashed rgba(15,23,42,.18);
    border-radius:6px;
    background:#fff;
}
.dash-big{ padding:16px 18px 14px; }

/* ====== 两栏 ====== */
.grid{
    display:grid;
    grid-template-columns: 1fr 1px 1.18fr;
    gap:18px;
    align-items:stretch;
}
.vline{ width:1px; background:rgba(15,23,42,.08); }

.col{ min-width:0; }
.col-qr{ padding:6px 6px 0; text-align:center; }
.col-info{ padding:6px 6px 0; }

/* 标题体系（原站更紧凑） */
.h1{
    font-size:13px;
    font-weight:800;
    color:#111827;
    line-height:1.2;
}
.h1-left{ text-align:left; }
.h2{
    margin-top:4px;
    font-size:11px;
    color:#6b7280;
}
.link{ color:#2563eb; font-weight:800; }

/* ====== Logo（中间） ====== */
.brand{
    margin-top:10px;
    display:flex;
    justify-content:center;
}
.brand-img{
    height:26px;
    width:auto;
    display:block;
    object-fit:contain;
}

/* ====== QR ====== */
.qr{
    margin:10px auto 8px;
    width:172px;
    height:172px;
    border:1px solid rgba(15,23,42,.14);
    border-radius:4px;
    display:flex;
    align-items:center;
    justify-content:center;
}
.qr-fake{
    width:152px;
    height:152px;
    border:1px solid rgba(15,23,42,.10);
    border-radius:3px;
    padding:8px;
    box-sizing:border-box;
    display:flex;
    flex-direction:column;
    gap:4px;
}
.qr-row{ display:flex; justify-content:space-between; gap:4px; }
.qr-dot{
    width:5px;
    height:5px;
    border-radius:1px;
    background:rgba(15,23,42,.22);
}

.qr-download{
    margin-bottom: 10px;
}

/* banks */
.banks{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
}
.bar{ color:rgba(15,23,42,.22); font-weight:700; }
.bank-img{ display:block; width:auto; object-fit:contain; }
.napas-img{ height:18px; }
.acb-img{ height:22px; }

.money{
    margin-top:10px;
    color:#ef4444;
    font-weight:900;
    font-size:18px;
}

.download-btn{
    margin-top:4px;
    min-width:132px;
    height:34px;
    padding:0 14px;
    border:none;
    border-radius:8px;
    background:#2563eb;
    color:#fff;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
    box-shadow:0 6px 14px rgba(37,99,235,.22);
}
.download-btn:active{
    transform:translateY(1px);
}

/* ====== 右侧表格（原站行更淡、更紧凑） ====== */
.tbl{ margin-top:8px; }
.tr{
    display:grid;
    grid-template-columns: 90px 1fr 84px;
    align-items:center;
    gap:10px;
    padding:9px 0;
    border-top:1px solid rgba(15,23,42,.06);
}
.k{
    color:#6b7280;
    font-size:12px;
}
.v{
    color:#111827;
    font-size:12px;
    font-weight:800;
    word-break:break-word;
}
.a{ display:flex; justify-content:flex-end; }

.copy{
    height:26px;
    min-width:64px;
    padding:0 12px;
    border:none;
    border-radius:6px;
    background:#16a34a;
    color:#fff;
    font-weight:800;
    font-size:12px;
    cursor:pointer;
}
.copy:active{ transform:translateY(1px); }

/* ====== 注意事项 ====== */
.dash-note{
    margin-top:14px;
    padding:12px 14px;
}
.note-title{
    color:#ef4444;
    font-weight:900;
    font-size:12px;
    margin-bottom:6px;
}
.note-list{
    margin:0;
    color:#374151;
    font-size:11px;
    line-height:1.65;
}
.red{ color:#ef4444; }
.b{ font-weight:900; }
.mono{
    font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
}

/* ====== 底部安全区 ====== */
.sec{
    text-align:center;
    margin-top:18px;
    padding-bottom:10px;
}
.sec-icons{
    display:flex;
    justify-content:center;
    gap:14px;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:8px;
}
.sec-badge{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0;
    background:transparent;
    border:none;
    box-shadow:none;
}
.sec-img{ display:block; width:auto; object-fit:contain; }
.sec1{ height:34px; }
.sec2{ height:100px; }

.sec-title{
    font-weight:900;
    color:#275B84;
    font-size:12px;
}
.sec-sub{
    margin-top:4px;
    font-size:11px;
    color:#275B84;
}

/* ====== 移动端适配 ====== */
@media (max-width:860px){
    /* ✅ 移动端顶部距离改为 16px */
    .top{
        padding:16px 12px 0;
    }
    .top-inner{
        grid-template-columns:1fr;
        gap:6px;
        text-align:center;
        padding-bottom:16px; /* ✅ 下方距离也 16px */
    }
    .top-left,.top-mid,.top-right{ justify-self:center; }
    .top-left{ justify-content:center; }

    /* ✅ 移动端隐藏顶部 logo */
    .top-right{
        display:none;
    }

    .card{ padding:12px; }
    .dash-big{ padding:14px; }

    .grid{
        grid-template-columns:1fr;
        gap:12px;
    }
    .vline{ display:none; }

    .qr{ width:210px; height:210px; }
    .qr-fake{ width:186px; height:186px; padding:10px; }

    .brand-img{ height:30px; }
    .napas-img{ height:20px; }
    .acb-img{ height:24px; }

    .tr{ grid-template-columns: 84px 1fr 80px; }
}

/* 超小屏 */
@media (max-width:360px){
    .qr{ width:190px; height:190px; }
    .qr-fake{ width:168px; height:168px; }
    .tr{ grid-template-columns:74px 1fr 76px; gap:8px; }
    .copy{ min-width:58px; padding:0 10px; }
}
</style>
