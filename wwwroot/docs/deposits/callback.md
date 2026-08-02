# @lang('apidoc.docs.deposits.callback.title')

### @lang('apidoc.docs.deposits.callback.t1')
- <p>@lang('apidoc.docs.deposits.callback.s1')</p>

### @lang('apidoc.docs.deposits.index.t2')
- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t8')
- <p>@lang('apidoc.docs.deposits.index.t6.content')</p>

### @lang('apidoc.docs.deposits.callback.t2')

| @lang('apidoc.docs.deposits.index.s2')                        | @lang('apidoc.docs.deposits.index.s3')     | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                                                                                                            |
|:--------------------------------------------------------------|:-------|:--------------------------------------:|:--------------------------------------------------------------------------------------------------------------------------------------------------|
| code                                                          | Number | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed')                                                                              | 
| message                                                       | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message')                                                                                                     |
| utr                                                           | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.utr')                                                                                                         |
| data                                                          | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data')                                                                                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;mid           | Number | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.return.mid')                                                                                                                    |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no            | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no')                                                                                                                            |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no      | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no')                                                                                                                      |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;amount        | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.deposit.amount')                                                                                                                |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;actual_amount | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.actual_amount')                                                                                                                 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;fee           | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.fee')                                                                                                                           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;created_time  | Number | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.created_time')                                                                                                                  |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;deposit_time  | Number | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.deposit_time')                                                                                                                  |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;notify_time   | Number | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.notify_time')                                                                                                                   |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;status        | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.status')，@lang('apidoc.api.inprogress')=>inprogress，@lang('apidoc.api.succeeded')=>succeeded，@lang('apidoc.api.failed')=>failed |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;extra         | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.extra')                                                                                                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;orig_amount   | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.orig_amount')                                                                                                                  |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;payer_name    | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.deposits.index.s6')                                                                                                            |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;sign          | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.sign')                                                                                                                          |

### @lang('apidoc.docs.deposits.callback.t3')

```json
{
    "code": 200,
    "message": "OK",
    "utr": "123123123",
    "data": {
        "mid": 21,
        "no": "D202504202090274764176358",
        "order_no": "CZ202542012093WFGI",
        "amount": "100.00",
        "actual_amount": "100.00",
        "fee": "4.50",
        "created_time": 1745122142,
        "deposit_time": 1745122200,
        "notify_time": 1745122206,
        "status": "succeeded",
        "extra": null,
        "orig_amount": "100.00",
        "payer_name": "张友",
        "sign": "N7QuBC5a1eOFqtxamA1vYbyPTsI="
    }
}
```

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "mid": 21,
        "no": "D202504202090274764176358",
        "order_no": "CZ202542012093WFGI",
        "amount": "100.00",
        "actual_amount": "0",
        "fee": "0",
        "created_time": 1745122142,
        "deposit_time": "",
        "notify_time": 1745122206,
        "status": "failed",
        "extra": null,
        "orig_amount": "100.00",
        "payer_name": "张友",
        "sign": "N7QuBC5a1eOFqtxamA1vYbyPTsI="
    }
}
```
### @lang('apidoc.callback_desc')
- @lang('apidoc.data_sign')
- @lang('apidoc.sign_key')：9a979c9975b056985cd7387604e7e23b
- @lang('apidoc.sign_string')：actual_amount=100.00&amount=100.00&created_time=1745122142&deposit_time=1745122200&fee=4.50&mid=21&no=D202504202090274764176358&notify_time=1&order_no=CZ202542012093WFGI&orig_amount=100.00&payer_name=张友&status=succeeded


