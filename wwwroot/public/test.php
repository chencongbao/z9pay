<?php


function superLongPublicKeyEncrypt($content, $rsaPublicKey, $choicePath = true, $withBase64 = false)
{
    if ($choicePath) {
        $pubKeyId = openssl_pkey_get_public($rsaPublicKey);//绝对路径读取
    } else {
        $pubKeyId = $rsaPublicKey;//公钥
    }
    $RSA_ENCRYPT_BLOCK_SIZE = 117;
    $result = '';
    $data = str_split($content, $RSA_ENCRYPT_BLOCK_SIZE);
    foreach ($data as $block) {
        openssl_public_encrypt($block, $dataEncrypt, $pubKeyId, OPENSSL_PKCS1_PADDING);
        $result .= $dataEncrypt;
    }

    if ($withBase64) {
        return base64_encode($result);
    } else {
        return $result;
    }
}

function superLongPrivateKeyDecrypt($content, $rsaPrivateKey, $choicePath = true, $withBase64 = false)
{
    if ($choicePath) {
        $priKeyId = openssl_pkey_get_private($rsaPrivateKey);//绝对路径
    } else {
        $priKeyId = $rsaPrivateKey;//私钥
    }

    if ($withBase64) {
        $data = base64_decode($content);
    }

    $RSA_DECRYPT_BLOCK_SIZE = 128;

    $result = '';
    $data = str_split($data, $RSA_DECRYPT_BLOCK_SIZE);
    foreach ($data as $block) {
        openssl_private_decrypt($block, $dataDecrypt, $priKeyId, OPENSSL_PKCS1_PADDING);
        $result .= $dataDecrypt;
    }

    if ($result) {
        return $result;
    } else {
        return false;
    }
}

$private_key = "-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDvluFNiF8IrIsddK0OXBAvVBJH11OKvy9er1tRGn9yEJoHCJY3
EU/xz2LasCK8AwgRIqGJbvDBgRa70c3QT9j+wPqNqqJCSoSEKifnDUk1RgUReJT6
iqWaJyfM+WM3aHnKl61RZL4NV5qKe4CHMtaH/JtBCC/JzpuFER1P1IhCtQIDAQAB
AoGAaFYQb68/k4twWbeB1YsKEVJPU7HV08pGWrmKztr3PTk1mnKG2BxV8DwcFJg3
yCCZ1rx6FFuXxOzudYR8WIctO4wdsEbFky/cEGsfc6JJjiktmZaQ7MvobGNwnoFJ
QvRxDd+5uD87JE19iBSgUpLVtXbv+pZxSpD70vitnMdSctECQQD66Z5HsuC8DUPu
OLQHNN4ra5Op179Xlq7LiEFW4GaVgonw24kiLX23c7CK7295Rgxct1fwQKyuU9br
n2uj8toDAkEA9HJ85BWlm2OfUm6VI3Q99rjlpCnhRyz70+sEtf7if1SpctVxNTkX
UOnXlpPTohjAHNhzh9fa1hh/ySH9sRMu5wJAa//8uh3br/YBxFsx2lw+OPBQGe4c
lSXtzPu0LCHg5f/PQhYs28I696jbV6IiGFA3Z/0e4/HiohLCUp9HJMWWYwJACE53
pfyCUyRwfomZccn6bQ7dZtWxfQyvRgU/dLvDkJYc5/UO0sMs4qf/lnNRhrmWlaRZ
UK1qF0pf1ULdbw360wJBAObrYopW2kvIlE09j9SEgNtgVsmfZlf85c4EAZrFJP/T
8nMNKQGo92Gd3HvbjJ+ZBOP1IFt+FDAsXeSLWLAwJrg=
-----END RSA PRIVATE KEY-----";

$public_key = "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDvluFNiF8IrIsddK0OXBAvVBJH
11OKvy9er1tRGn9yEJoHCJY3EU/xz2LasCK8AwgRIqGJbvDBgRa70c3QT9j+wPqN
qqJCSoSEKifnDUk1RgUReJT6iqWaJyfM+WM3aHnKl61RZL4NV5qKe4CHMtaH/JtB
CC/JzpuFER1P1IhCtQIDAQAB
-----END PUBLIC KEY-----
";

$content = "hello world";
$res = superLongPublicKeyEncrypt($content, $public_key, false, true);
var_dump($res);
$res = superLongPrivateKeyDecrypt($res, $private_key, false, true);
var_dump($res);
