<template>
    <div class="bottom-actions">
        <!-- 占位器：避免内容被固定栏遮挡 -->
        <div class="bottom-spacer" aria-hidden="true"/>
        <!-- 固定栏 -->
        <div class="bottom-fixed-bar">
            <div class="action-buttons">
                <van-button type="primary" round class="btn-confirm" v-on:click="showUpload = true">
                    {{ $t('cashier.confirm_pay') }}
                </van-button>
                <van-button type="danger" plain round class="btn-cancel" v-on:click="cancel">
                    {{ $t('cashier.canncel_pay') }}
                </van-button>
            </div>
        </div>

        <van-dialog v-model="showUpload" :title="$t('cashier.upload_pay_img')" :show-confirm-button="false"
                    show-cancel-button :cancel-button-text="$t('cashier.cannel_upload_pay_img')"
                    cancel-button-color="#1989fa">
            <div style="text-align: center;padding: 20px;">
                <van-uploader :after-read="afterRead" :max-size="5 * 1000 * 1024" @oversize="onOversize"/>
            </div>
        </van-dialog>
    </div>
</template>

<script>
import {Toast, Dialog} from "vant";

export default {
    name: "BottomBarActions",
    data() {
        return {
            barHeight: 64,
            showUpload: false
        }
    },
    emits: ['finish'],
    methods: {
        afterRead(file) {
            const formData = new FormData();
            formData.append('file', file.file);
            formData.append('ordernumber', this.$config.ordernumber);
            this.$ajax.post("cashier/upload/certificate", formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }).then((res) => {
                if (res.code == 200) {
                    if (res.data.return_url) {
                        Dialog.confirm({
                            title: "提示",
                            confirmButtonText: "跳转商家",
                            showCancelButton:false,
                            message: "操作成功",
                        }).then(() => {
                            window.location.replace(res.data.return_url);
                        }).catch(error => {
                            this.$emit("finish", {step: 3, msg: res.message});
                        });
                    } else {
                        this.$emit("finish", {step: 3, msg: this.$t('cashier.submit_success')});
                    }
                } else {
                    this.$emit("finish", {step: 4, msg: res.message});
                }
            }).catch(error => {
                this.$emit("finish", {step: 4, msg: error});
            });
        },
        onOversize(file) {
            Toast(this.$t('cashier.upload_limit'));
        },
        cancel() {
            Dialog.confirm({
                title: this.$t('cashier.canncel_title'),
                confirmButtonText: this.$t('cashier.canncel_confirm'),
                cancelButtonText: this.$t('cashier.cannel_cannel'),
                message: this.$t('cashier.cannel_action'),
            }).then(() => {
                this.$ajax.get("cashier/deposit/cancel", {
                    params: {ordernumber: this.$config.ordernumber},
                }).then((res) => {
                    if (res.code == 200) {
                        if (res.data.return_url) {
                            Dialog.confirm({
                                title: "提示",
                                confirmButtonText: "跳转商家",
                                showCancelButton:false,
                                message: "操作成功",
                            }).then(() => {
                                window.location.replace(res.data.return_url);
                            }).catch(error => {
                                this.$emit("finish", {step: 3, msg: res.message});
                            });
                        } else {
                            this.$emit("finish", {step: 3, msg: res.message});
                        }
                    } else {
                        this.$emit("finish", {step: 4, msg: res.message});
                    }
                }).catch((error) => {
                    this.$emit("finish", {step: 4, msg: error});
                });
            });
        }
    }
};
</script>

<style lang="less" scoped>
@bar-h: 64px; // 固定高度常量

.bottom-spacer {
    height: calc(@bar-h + env(safe-area-inset-bottom));
}

/* 固定底部操作区 */
.bottom-fixed-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: calc(var(@bar-h) + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.08);
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;

    .action-buttons {
        width: 100%;
        max-width: 520px;
        display: flex;
        gap: 14px;
    }

    .btn-confirm,
    .btn-cancel {
        flex: 1;
        height: 44px;
        font-weight: 700;
        border-radius: 10px;
    }

    .btn-confirm {
        background: #1f6ef2;
        color: #fff;
        border: 1px solid #1f6ef2;
    }

    .btn-cancel {
        background: #fff;
        color: #e53935;
        border: 1px solid #e53935;
    }
}
</style>
