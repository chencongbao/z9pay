# @lang('apidoc.docs.deposits.index.title')

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/deposits</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2')            | @lang('apidoc.docs.deposits.index.s9') | @lang('apidoc.docs.deposits.index.s5') |
|:--------------|:---------------------------------------|:---------------------------------------|
| Authorization | api-key 720cf440ed3a01f5               | @lang('apidoc.api.Authorization')，@lang('apidoc.api.getmethod')      |
| Content-Type  | application/json                       | application/json                       |

> @lang('apidoc.docs.readme.header_tip')

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2')        | @lang('apidoc.docs.deposits.index.s3')  | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
|:----------------------------------------------|:----------------------------------------|:--------------------------------------:|:---------------------------------------|
| mid                                           | Number                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.mid')                |
| amount                                        | String                                   | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.deposit.amount')     |
| order_no                                      | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.order_no')           |
| gateway                                       | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.gateway')，@lang('apidoc.api.getmethod')            |
| ip                                            | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.ip')                 |
| notify_url                                    | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.notify_url')         |
| return_url                                    | String                                  | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.return_url')         |
| sign                                          | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign')               |
| name                                          | String                                  | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.deposits.index.s6') |
| bank_name                                     | String                                  | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bank_name') |
| card_no                                       | String                                  | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.card_no') |
| card_name                                     | String                                  | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.card_name') |

#### @lang('apidoc.docs.deposits.index.s10')

#### @lang('apidoc.docs.deposits.index.s11')

#### @lang('apidoc.docs.deposits.index.s12')

#### @lang('apidoc.docs.deposits.index.s13')

### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "amount": "300.00",
    "order_no": "Test1629186205915",
    "gateway": "test",
    "ip": "127.0.0.1",
    "notify_url": "http://localhost:8088/notify",
    "sign": "VEoBHy5qXXyE4KT+yjBbCJ8sZ9Q="
}
```

### @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2')                     | @lang('apidoc.docs.deposits.index.s3')     | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                               |
|:-----------------------------------------------------------|:-------|:---:|:---------------------------------------------------------------------|
| code                                                       | Number |  @lang('apidoc.docs.deposits.index.s7')  | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') | 
| message                                                    | String |  @lang('apidoc.docs.deposits.index.s7')  | @lang('apidoc.api.responses.success.message')                        |
| data                                                       | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.responses.success.data')                           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;url        | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.url')                                              |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no         | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.no')                                               |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no   | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.order_no')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_name   | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.docs.deposits.index.s6')                               |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankCode   | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.bankCode')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankName   | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.bankName')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankBranch | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.bankBranch')                                       |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardNo     | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.cardNo')                                           |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardName   | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.cardName')                                         |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;qrCodeUrl  | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.api.qrCodeUrl')                                        |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_amount | String | @lang('apidoc.docs.deposits.index.s8')  | @lang('apidoc.docs.pay_amount')                                                     |


### @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "no": "3202108171643502213746",
        "bankCode": "ICBC",
        "bankName": "工商银行",
        "cardNo": "62*22*240*004*79",
        "cardName": "邓*亮",
        "qrCodeUrl": "",
        "bankBranch": "",
        "order_no": "1427547422346711040",
        "pay_name": "张友",
        "url": "https://{api域名}/xxxxx/xxxxx"
    }
}
```



