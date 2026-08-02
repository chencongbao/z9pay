# @lang('apidoc.docs.coder.title')


### @lang('apidoc.docs.coder.s1')

```java
package org.apache.commons.codec.digest;
public String sign (String secretKey, String data) {
    byte[] bytes = HmacUtils.hmacSha1(secretKey, data);
    return Base64.getEncoder().encodeToString(bytes);
}
```


### @lang('apidoc.docs.coder.s2')

```php
static public function sign($data, $secretKey)
{
    ksort($data);
    reset($data);
    $string = '';
    foreach ($data as $key => $val) {
        if ($val == '' || $key == 'sign') {
            continue;
        }
        $string .= "{$key}={$val}&";
    }
    $string = trim($string, "&");
    return base64_encode(hash_hmac('sha1', $string, $secretKey, true));
}
```

### @lang('apidoc.docs.coder.s3')

```csharp
private string CreateSign(string message, string key)
{
    var encoding = new System.Text.UTF8Encoding();
    byte[] keyByte = encoding.GetBytes(key);
    byte[] messageBytes = encoding.GetBytes(message);
    var hmacsha = new System.Security.Cryptography.HMACSHA1(keyByte);
    byte[] hashmessage = hmacsha.ComputeHash(messageBytes);
    return Convert.ToBase64String(hashmessage);
}
```

### @lang('apidoc.docs.coder.s4')

```python
import base64
import hmac
from hashlib import sha1


def sign(data: dict, secret_key: str) -> str:
    values = []
    for key in sorted(data.keys()):
        value = data[key]
        if value in ("", None) or key == "sign":
            continue
        values.append(f"{key}={value}")

    message = "&".join(values)
    digest = hmac.new(secret_key.encode("utf-8"), message.encode("utf-8"), sha1).digest()
    return base64.b64encode(digest).decode("utf-8")
```

### @lang('apidoc.docs.coder.s5')

```go
package main

import (
    "crypto/hmac"
    "crypto/sha1"
    "encoding/base64"
    "sort"
    "strings"
)

func Sign(data map[string]string, secretKey string) string {
    keys := make([]string, 0, len(data))
    for key, value := range data {
        if value == "" || key == "sign" {
            continue
        }
        keys = append(keys, key)
    }

    sort.Strings(keys)

    values := make([]string, 0, len(keys))
    for _, key := range keys {
        values = append(values, key+"="+data[key])
    }

    message := strings.Join(values, "&")
    mac := hmac.New(sha1.New, []byte(secretKey))
    mac.Write([]byte(message))
    return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}
```

### @lang('apidoc.docs.coder.s6')

```javascript
const crypto = require("crypto");

function sign(data, secretKey) {
  const message = Object.keys(data)
    .sort()
    .filter((key) => data[key] !== "" && data[key] !== null && key !== "sign")
    .map((key) => `${key}=${data[key]}`)
    .join("&");

  return crypto.createHmac("sha1", secretKey).update(message, "utf8").digest("base64");
}
```

### @lang('apidoc.docs.coder.s7')

```javascript
async function sign(data, secretKey) {
  const message = Object.keys(data)
    .sort()
    .filter((key) => data[key] !== "" && data[key] !== null && key !== "sign")
    .map((key) => `${key}=${data[key]}`)
    .join("&");

  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secretKey),
    { name: "HMAC", hash: "SHA-1" },
    false,
    ["sign"],
  );

  const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(message));
  return btoa(String.fromCharCode(...new Uint8Array(signature)));
}
```
