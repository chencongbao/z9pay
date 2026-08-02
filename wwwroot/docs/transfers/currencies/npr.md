# @lang('apidoc.currency.npr')

> @lang('apidoc.docs.currency.transfer_intro_generic')
> @lang('apidoc.docs.currency.common_ref')

## @lang('apidoc.docs.currency.full_params')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| mid | Number | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.mid') |
| amount | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.deposit.amount') |
| order_no | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.order_no') |
| ip | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.ip') |
| notify_url | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.notify_url') |
| bank_code | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.bank_code')，@lang('apidoc.api.getmethod') |
| card_no | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.cardNo') |
| holder_name | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.cardName') |
| sign | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign') |

## @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "amount": "300.00",
    "order_no": "TEST202605070001",
    "ip": "127.0.0.1",
    "notify_url": "http://localhost:8088/notify",
    "bank_code": "ICBC",
    "card_no": "62284808584935671",
    "holder_name": "test",
    "sign": "q1ANw2987a1Y647PTJ4MFOARVnc="
}
```

## @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| code | Number | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') |
| message | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message') |
| data | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no') |

## @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "no": "3202108171643502213746",
        "order_no": "1427547422346711040"
    }
}
```
