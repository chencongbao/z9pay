# @lang('apidoc.docs.transfers.callback.title')

### @lang('apidoc.docs.deposits.callback.t1')

- <p>@lang('apidoc.docs.deposits.callback.s1')</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t8')

- <p>@lang('apidoc.docs.deposits.index.t6.content')</p>

### @lang('apidoc.docs.deposits.callback.t2')

| @lang('apidoc.docs.deposits.index.s2')                        | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                                                                                                            |
|:--------------------------------------------------------------|:---------------------------------------|:--------------------------------------:|:--------------------------------------------------------------------------------------------------------------------------------------------------|
| code                                                          | Number                                 | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed')                                                                              | 
| message                                                       | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message')                                                                                                     |
| fail_reason                                                   | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.fail_reason')                                                                                                 |
| utr                                                           | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.utr')                                                                                                         |
| data                                                          | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data')                                                                                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;mid           | Number                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.return.mid')                                                                                                                    |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no            | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no')                                                                                                                            |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no      | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no')                                                                                                                      |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;amount        | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.deposit.amount')                                                                                                                |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;actual_amount | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.actual_amount')                                                                                                                 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;fee           | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.fee')                                                                                                                           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;created_time  | Number                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.created_time')                                                                                                                  |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;transfer_time | Number                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.deposit_time')                                                                                                                  |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;notify_time   | Number                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.notify_time')                                                                                                                   |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;status        | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.status')，@lang('apidoc.api.inprogress')=>inprogress，@lang('apidoc.api.succeeded')=>succeeded，@lang('apidoc.api.failed')=>failed |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;extra         | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.extra')                                                                                                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;from_card_no  | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.from_card_no')                                                                                                                 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;sign          | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.sign')                                                                                                                          |

### @lang('apidoc.docs.deposits.callback.t3')

```json
{
    "code": 200,
    "message": "OK",
    "fail_reason": "",
    "utr": "",
    "data": {
        "mid": 435,
        "no": "T202506181201029149917467008",
        "order_no": "SGOUT1935185959544946688",
        "amount": "100.00",
        "actual_amount": "100.00",
        "fee": "1.00",
        "created_time": 1750219262,
        "transfer_time": 1750219288,
        "notify_time": 1750219328,
        "status": "succeeded",
        "extra": null,
        "from_card_no": "",
        "sign": "8gsSwgbFrHWuETCSnjeDPmaQpOE="
    }
}
```

```json
{
    "code": 200,
    "message": "OK",
    "fail_reason": "代付失败",
    "data": {
        "mid": 435,
        "no": "T202506181201029149917467008",
        "order_no": "SGOUT1935185959544946688",
        "amount": "100.00",
        "actual_amount": "0",
        "fee": "0",
        "created_time": 1750219262,
        "transfer_time": "",
        "notify_time": 1750219328,
        "status": "failed",
        "extra": null,
        "from_card_no": "",
        "sign": "8gsSwgbFrHWuETCSnjeDPmaQpOE="
    }
}
```

### @lang('apidoc.callback_desc')

- @lang('apidoc.data_sign')
- @lang('apidoc.sign_key')：068228789aa823152f265f086c47fc61
- @lang('apidoc.sign_string')
  ：actual_amount=100.00&amount=100.00&created_time=1750219262&fee=0.00&mid=435&no=T202506181201029149917467008&notify_time=1750219328&order_no=SGOUT1935185959544946688&status=succeeded&transfer_time=1750219288


