# @lang('apidoc.docs.deposits.submit_utr.title')

### @lang('apidoc.docs.deposits.index.t1')

- <p>https://{@lang('apidoc.docs.deposits.index.s1')}/api/v3/deposits/cashier/utr</p>

### @lang('apidoc.docs.deposits.index.t2')

- <p>application/json   POST</p>

### @lang('apidoc.docs.deposits.index.t3')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s9') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- |
| Authorization | api-key 720cf440ed3a01f5 | @lang('apidoc.api.Authorization')，@lang('apidoc.api.getmethod') |
| Content-Type | application/json | application/json |

### @lang('apidoc.docs.deposits.index.t4')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| mid | Number | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.mid') |
| order_no | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.order_no') |
| utr | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.utr') |
| sign | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.sign') |

### @lang('apidoc.docs.deposits.index.t5')

```json
{
    "mid": "1",
    "order_no": "TEST202605081640105429790",
    "utr": "1234567890",
    "sign": "ZwPH8aGntuBapKIYkRmf42r43v8="
}
```

### @lang('apidoc.docs.deposits.index.t6')

| @lang('apidoc.docs.deposits.index.s2') | @lang('apidoc.docs.deposits.index.s3') | @lang('apidoc.docs.deposits.index.s4') | @lang('apidoc.docs.deposits.index.s5') |
| --- | --- | --- | --- |
| code | Number | @lang('apidoc.docs.deposits.index.s7') | 200=>@lang('apidoc.api.succeeded')，-9999=>@lang('apidoc.api.failed') |
| message | String | @lang('apidoc.docs.deposits.index.s7') | @lang('apidoc.api.responses.success.message') |
| data | Object | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.responses.success.data') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return_url | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.return_url') |

### @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "return_url": "https://example.com/return"
    }
}
```
