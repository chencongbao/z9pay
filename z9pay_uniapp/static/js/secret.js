import CryptoJS from 'crypto-js';

/**
 * 接口数据加密函数
 * @param str string 需加密的json字符串
 * @param key string 加密key(16位)
 * @param iv string 加密向量(16位)
 * @return string 加密密文字符串
 */
function encrypt(str, key = "1234123412ABCDEF", iv = "ABCDEF1234123412") {
  //密钥16位
  var key = CryptoJS.enc.Utf8.parse(key);
  //加密向量16位
  var iv = CryptoJS.enc.Utf8.parse(iv);
  var encrypted = CryptoJS.AES.encrypt(str, key, {
    iv: iv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.ZeroPadding
  });
  return encrypted.toString();
}

/**
 * 接口数据解密函数
 * @param str string 已加密密文
 * @param key string 加密key(16位)
 * @param iv string 加密向量(16位)
 * @returns {*|string} 解密之后的json字符串
 */
function decrypt(str, key = "1234123412ABCDEF", iv = "ABCDEF1234123412") {
  //密钥16位
  var key = CryptoJS.enc.Utf8.parse(key);
  //加密向量16位
  var iv = CryptoJS.enc.Utf8.parse(iv);
  var decrypted = CryptoJS.AES.decrypt(str, key, {
    iv: iv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.ZeroPadding
  });
  return trim(CryptoJS.enc.Utf8.stringify(decrypted).toString());
}


function trim(str) {
  return str.replace(/\s+/g, "").replace(/ss*$/, "").replace(/\u0000|\u0001|\u0002|\u0003|\u0004|\u0005|\u0006|\u0007|\u0008|\u0009|\u000a|\u000b|\u000c|\u000d|\u000e|\u000f|\u0010|\u0011|\u0012|\u0013|\u0014|\u0015|\u0016|\u0017|\u0018|\u0019|\u001a|\u001b|\u001c|\u001d|\u001e|\u001f/g, "");
}


export default {
  lock: encrypt,
  unlock: decrypt
}