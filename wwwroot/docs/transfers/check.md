# @lang('apidoc.api.transfercheck.title')

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/transfers/check</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s9') | @lang('apidoc.docs.deposits.index.s5')                          |
|:---------------------------------------|:---------------------------------------|:----------------------------------------------------------------|
| Content-Type                           | application/json                       | application/json                                                |

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
|:---------------------------------------|:---------------------------------------|:--------------------------------------:|:---------------------------------------|
| cid                                    | Number                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.cid')                |
| amount                                 | String                                  | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.deposit.amount')     |
| ordernumber                            | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.ordernumber')           |
| sign                                   | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign')               |


### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "cid": "1",
    "amount": "300.00",
    "ordernumber": "Test1629186205915",
    "sign": "q1ANw2987a1Y647PTJ4MFOARVnc="
}
```

### @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2')                   | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5')                               |
|:---------------------------------------------------------|:---------------------------------------|:--------------------------------------:|:---------------------------------------------------------------------|
| code                                                     | Number                                 | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') | 
| message                                                  | String                                 | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message')                        | |

### @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK"
}

```







