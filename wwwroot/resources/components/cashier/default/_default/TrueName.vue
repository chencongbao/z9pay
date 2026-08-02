<template>
    <pay-wrapper :name="$t('cashier.pay_name')">
        <div class="main">
            <div class="name">{{ $t('cashier.please_input_ningde') }}<span>{{ $t('cashier.pay_name') }}</span></div>
            <van-form class="form" @submit="onSubmit">
                <van-field
                    v-model="pay_name"
                    name="pay_name"
                    v-bind:placeholder="$t('cashier.please_input_pay_name')"
                    size="large"
                    maxlength="50"
                    :rules="[{ required: true, message: $t('cashier.pay_name_not_null') }]"
                />
                <div class="btn-wrap">
                    <van-button
                        block
                        type="primary"
                        native-type="submit"
                        :loading="submitting"
                        :loading-text="$t('cashier.submiting')"
                        :disabled="submitting"
                        class="submit-btn"
                    >
                        {{ $t('cashier.submit') }}
                    </van-button>
                </div>
            </van-form>

            <div class="notice">
                <div class="notice-title">{{ $t('cashier.notice_title') }}</div>
                <ul class="notice-list">
                    <li class="red">{{ $t('cashier.notice_s1') }}</li>
                    <li>{{ $t('cashier.notice_s2') }}</li>
                    <li>{{ $t('cashier.notice_s3') }}</li>
                </ul>
            </div>
        </div>
    </pay-wrapper>
</template>


<script>
export default {
    name: 'TrueName',
    data() {
        return {
            pay_name: "",
            submitting: false
        }
    },
    methods: {
        onSubmit() {
            this.submitting = true;
            this.$ajax.post("cashier/deposit/payname", {
                ordernumber: this.$config.ordernumber,
                pay_name: this.pay_name,
                locale: this.$config.locale
            }).then((res) => {
                if (res.code == 200) {
                    if (res.data.channel_pay_url) {
                        window.location.replace(res.data.channel_pay_url);
                    } else {
                        this.$emit(
                            "submit",
                            Object.assign(
                                {step: 2, msg: res.message},
                                res.data
                            )
                        );
                    }
                } else {
                    this.$emit("submit", {step: 1, msg: res.message});
                }
            }).catch(error => {
                this.$emit("submit", {step: 1, msg: error});
            });
        },
    },
}
</script>

<style lang="less" scoped>
.main {
    > .title {
        text-align: center;
        font-size: 16px;
        color: red;
        padding-bottom: 20px;
    }

    > .name {
        text-align: center;
        font-size: 16px;
        color: #000000;
        padding-bottom: 20px;

        > span {
            color: #3272ff;
        }
    }

    > .form {
        ::v-deep .van-field__control {
            font-size: 16px !important;
        }

        .tip {
            padding: 10px 10px;
            font-size: 12px;

            > span {
                color: #3272ff;
            }
        }
    }

    /* 按钮 */

    .btn-wrap {
        margin-top: 20px;
    }

    .submit-btn {
        height: 44px;
        border-radius: 8px;
        background: #1f6ef2 !important;
        border-color: #1f6ef2 !important;
        color: #fff;
        font-weight: 700;
    }

    /* 新增：注意事项块 */

    .notice {
        background: #fff;
        margin-top: 16px;
        padding: 14px 14px;
        border: 1px solid #e6ebf5;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(31, 110, 242, 0.08);
    }

    .notice-title {
        font-weight: 700;
        color: #1f6ef2;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .notice-list {
        font-size: 12px;
        color: #555;
        line-height: 1.6;
        padding-left: 16px;
    }

    .notice-list li {
        list-style: disc;

        &.red {
            color: red;
        }
    }
}
</style>
