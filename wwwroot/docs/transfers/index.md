# @lang('apidoc.docs.transfers.index.title')

<p style="color: #ff4d4f; font-weight: 700;">@lang('apidoc.docs.transfers.index.notice')</p>

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/transfers</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s9') | @lang('apidoc.docs.deposits.index.s5')                          |
|:---------------------------------------|:---------------------------------------|:----------------------------------------------------------------|
| Authorization                          | api-key 720cf440ed3a01f5               | @lang('apidoc.api.Authorization')，@lang('apidoc.api.getmethod') |
| Content-Type                           | application/json                       | application/json                                                |

> @lang('apidoc.docs.readme.header_tip')

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2')      | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
|:--------------------------------------------|:---------------------------------------|:--------------------------------------:|:---------------------------------------|
| mid                                         | Number                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.mid')                |
| amount                                      | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.deposit.amount')     |
| order_no                                    | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.order_no')           |
| ip                                          | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.ip')                 |
| notify_url                                  | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.notify_url')         |
| bank_code                                   | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.bank_code')，@lang('apidoc.api.getmethod')          |
| card_no                                     | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.cardNo')             |
| holder_name                                 | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.cardName')           |
| sign                                        | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign')               |
| bank_name                                   | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankName')           |
| bank_branch                                 | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankBranch')         |
| identity_no                                 | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.identity_no')         |
| withdrawQueryUrl                            | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.withdrawQueryUrl')         |


#### @lang('apidoc.docs.question.t14')
- <p>bank_code=OB</p>
- <p>@lang('apidoc.docs.question.s14')</p>
- <p>@lang('apidoc.docs.question.s15')</p>
- <p>@lang('apidoc.docs.question.s16')</p>
- <p>@lang('apidoc.docs.question.s17')</p>

#### @lang('apidoc.docs.question.t5')
- <p>bank_code=OB</p>
- <p>@lang('apidoc.docs.question.s9')</p>
- <p>@lang('apidoc.docs.question.s10')</p>

#### @lang('apidoc.docs.question.t6')
- <p>@lang('apidoc.docs.question.s12')</p>

#### @lang('apidoc.docs.question.t7')
- <p>@lang('apidoc.docs.question.s13')</p>

### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "amount": "300.00",
    "order_no": "Test1629186205915",
    "bank_code": "ABC",
    "holder_name": "test",
    "card_no": "622848085*84935*671",
    "ip": "127.0.0.1",
    "notify_url": "http://localhost:8088/notify",
    "sign": "q1ANw2987a1Y647PTJ4MFOARVnc="
}
```

### @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2')                   | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                               |
|:---------------------------------------------------------|:---------------------------------------|:--------------------------------------:|:---------------------------------------------------------------------|
| code                                                     | Number                                 | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') | 
| message                                                  | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message')                        |
| data                                                     | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data')                           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no       | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no')                                               |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no | String                                 | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no')                                         |

### @lang('apidoc.docs.deposits.index.t7')

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


