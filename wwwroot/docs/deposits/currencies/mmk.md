# @lang('apidoc.currency.mmk')

> @lang('apidoc.docs.currency.deposit_intro_generic')
> @lang('apidoc.docs.currency.common_ref')

## @lang('apidoc.docs.currency.full_params')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| mid | Number | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.mid') |
| amount | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.deposit.amount') |
| order_no | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.order_no') |
| gateway | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.gateway')，@lang('apidoc.api.getmethod') |
| ip | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.ip') |
| notify_url | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.notify_url') |
| sign | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign') |

## @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "amount": "300.00",
    "order_no": "TEST202605070001",
    "gateway": "test",
    "ip": "127.0.0.1",
    "notify_url": "http://localhost:8088/notify",
    "sign": "ZwPH8aGntuBapKIYkRmf42r43v8="
}
```

## @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| code | Number | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') |
| message | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message') |
| data | Object | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;url | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.url') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_name | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.deposits.index.s6') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_amount | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.pay_amount') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankCode | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankCode') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankName | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankName') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankBranch | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankBranch') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardNo | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.cardNo') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardName | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.cardName') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;qrCodeUrl | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.qrCodeUrl') |
