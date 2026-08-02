# @lang('apidoc.currency.inr')

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
    "order_no": "TEST202605080001",
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
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.no') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order_no | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.order_no') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_name | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.deposits.index.s6') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pay_amount | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.pay_amount') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;url | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.url') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankCode | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankCode') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankBranch | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankBranch') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bankName | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.bankName') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardNo | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.cardNo') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;cardName | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.cardName') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;qrCodeUrl | String | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.api.qrCodeUrl') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;appUrl | Object | @lang('apidoc.docs.deposits.index.s8') | @lang('apidoc.docs.appUrl') |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;paytmmp | String | @lang('apidoc.docs.deposits.index.s8') | Paytm app deeplink |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;phonepe | String | @lang('apidoc.docs.deposits.index.s8') | PhonePe app deeplink |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;uri | String | @lang('apidoc.docs.deposits.index.s8') | UPI universal deeplink |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;gpay | String | @lang('apidoc.docs.deposits.index.s8') | Google Pay app deeplink |
| errorcode | Number | @lang('apidoc.docs.deposits.index.s8') | Downstream error code |

## @lang('apidoc.docs.deposits.index.t7')

```json
{
    "code": 200,
    "message": "OK",
    "data": {
        "no": "D202605081640107253491",
        "order_no": "TEST202605081640105429790",
        "pay_amount": "100",
        "url": "https:\/\/cashier.isdkjfgsjdufyghiahf.com\/merchant\/blue?merchantId=2147483657&merchantOrderNo=C2026050814101066240940",
        "bankCode": "",
        "bankBranch": "",
        "bankName": "",
        "cardNo": "9955278545@mbkns",
        "cardName": "",
        "qrCodeUrl": "",
        "appUrl": {
            "paytmmp": "paytmmp:\/\/cash_wallet?pa=9955278545%40mbkns&pn=Prince+kumar&tn=pefVXccCK&am=100.00&cu=INR&featuretype=money_transfer",
            "phonepe": "phonepe:\/\/native?data=eyJjb250YWN0Ijp7ImNic05hbWUiOiIiLCJ2cGEiOiI5OTU1Mjc4NTQ1QG1ia25zIiwibmlja05hbWUiOiIiLCJ0eXBlIjoiVlBBIn0sInAycFBheW1lbnRDaGVja291dFBhcmFtcyI6eyJub3RlIjoicGVmVlhjY0NLIiwiaXNCeURlZmF1bHRLbm93bkNvbnRhY3QiOnRydWUsImVuYWJsZVNwZWVjaFRvVGV4dCI6ZmFsc2UsImFsbG93QW1vdW50RWRpdCI6ZmFsc2UsInNob3dRckNvZGVPcHRpb24iOmZhbHNlLCJkaXNhYmxlVmlld0hpc3RvcnkiOnRydWUsInNob3VsZFNob3dVbnNhdmVkQ29udGFjdEJhbm5lciI6ZmFsc2UsImlzUmVjdXJyaW5nIjpmYWxzZSwiY2hlY2tvdXRUeXBlIjoiREVGQVVMVCIsInRyYW5zYWN0aW9uQ29udGV4dCI6InAycCIsImluaXRpYWxBbW91bnQiOjEwMDAwLCJkaXNhYmxlTm90ZXNFZGl0Ijp0cnVlLCJzaG93S2V5Ym9hcmQiOnRydWUsImN1cnJlbmN5IjoiSU5SIiwic2hvdWxkU2hvd01hc2tlZE51bWJlciI6dHJ1ZX19&id=p2ppayment",
            "gpay": "gpay:\/\/upi\/pay?pa=9955278545%40mbkns&am=100.00&cu=INR&pn=Prince+kumar&tr=C2026050814101066240940",
            "uri": "upi:\/\/pay?pa=9955278545%40mbkns&pn=Prince+kumar&am=100.00&cu=INR&tr=C2026050814101066240940&tn=pefVXccCK"
        }
    },
    "errorcode": 0
}
```

