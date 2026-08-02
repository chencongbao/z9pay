# @lang('apidoc.docs.deposits.query.title')

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/deposits/query</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2')            | @lang('apidoc.docs.deposits.index.s9')                        | @lang('apidoc.docs.deposits.index.s5')                                |
|:--------------|:-------------------------|:----------------------------------|
| Authorization | api-key 720cf440ed3a01f5 | @lang('apidoc.api.Authorization')，@lang('apidoc.api.getmethod')  |
| Content-Type  | application/json         | application/json                  |

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2')       | @lang('apidoc.docs.deposits.index.s3')     | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')              |
|:---------|:-------|:--:|:----------------|
| mid      | Number | @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.mid')            |
| order_no | String | @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.order_no')         |
| sign     | String | @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.sign') |

### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "order_no": "Test1629186205915",
    "sign": "VZFA4vX+f4PDv3h7siZjCdu1h4s="
}
```

###  @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2')                        | @lang('apidoc.docs.deposits.index.s3')     |                  @lang('apidoc.docs.deposits.index.s4')                   | @lang('apidoc.docs.deposits.index.s5')                                            |
|:--------------------------------------------------------------|:-------|:--------------------------------------:|:----------------------------------------------|
| code                                                          | Number | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed')                             | 
| message                                                       | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message')                                          |
| data                                                          | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data')                                     |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;mid           | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.return.mid')                                          |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no            | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.no')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no      | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.order_no')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_name      | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.docs.deposits.index.s6')                               |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;amount        | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.deposit.amount')                                          |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;actual_amount | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.actual_amount')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;fee           | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.fee')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;created_time  | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.created_time')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;deposit_time  | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.deposit_time')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;notify_time   | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.notify_time')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;utr           | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.responses.success.utr')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;status        | String |                   @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.status')，@lang('apidoc.api.inprogress')=>inprogress，@lang('apidoc.api.succeeded')=>succeeded，@lang('apidoc.api.failed')=>failed |

### @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "amount": 300,
        "actual_amount": 300,
        "fee": 0,
        "status": "failed",
        "mid": 1,
        "no": "120210817154327105296902",
        "order_no": "Test1629186205915",
        "pay_name": "张友",
        "notify_time": 1721532280,
        "deposit_time": 1721532280,
        "created_time": 1721532280
    }
}

```
