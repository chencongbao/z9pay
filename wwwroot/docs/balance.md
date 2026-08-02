# @lang('apidoc.docs.balance.title')

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/balance</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2')            | @lang('apidoc.docs.deposits.index.s9')                        | @lang('apidoc.docs.deposits.index.s5')                                |
|:--------------|:-------------------------|:----------------------------------|
| Authorization | api-key 720cf440ed3a01f5 | @lang('apidoc.api.Authorization')，@lang('apidoc.api.getmethod') |
| Content-Type  | application/json         | application/json                  |

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2')         | @lang('apidoc.docs.deposits.index.s3')     | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                                |
|:-----------|:-------|:--:|:----------------------------------|
| mid        | Number | @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.mid')                              |
| sign       | String | @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.sign')                   |

### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "sign": "ttxhm4qInC4KZXcBxxkIJ87tkZE="
}
```

### @lang('apidoc.docs.deposits.index.t8')

| @lang('apidoc.docs.deposits.index.s2')                                                                | @lang('apidoc.docs.deposits.index.s3')     |                  @lang('apidoc.docs.deposits.index.s4')                   | @lang('apidoc.docs.deposits.index.s5')                |
|:------------------------------------------------------------------------------------------------------|:-------|:-------------------------------------------------------------------------:|:------------------|
| code                                                                                                  | Number |                  @lang('apidoc.docs.deposits.index.s7')                   | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') | 
| message                                                                                               | String |                  @lang('apidoc.docs.deposits.index.s7')                   | @lang('apidoc.api.responses.success.message')              |
| data                                                                                                  | String |                  @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.responses.success.data')         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;balance                                               | String |                  @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.balance')           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;available_balance                                     | String |                  @lang('apidoc.docs.deposits.index.s8')                   | @lang('apidoc.api.available_balance')           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;poll_interval                                         | Number |                  @lang('apidoc.docs.deposits.index.s8')                   | 建议余额查询轮询间隔，单位秒；请勿低于 2 秒        |

### @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "balance": 300,
        "available_balance": 300,
        "poll_interval": 2
    }
}

```
