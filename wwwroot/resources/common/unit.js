export function isMobile() {
    return navigator.userAgent.match(
        /(phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone)/i
    );
}

export function isPayMobile() {
    return navigator.userAgent.match(
        /(Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini)/i
    );
}

export function getUrlRelativePath() {
    var url = document.location.toString();
    return url;
    var arrUrl = url.split("//");

    var start = arrUrl[1].indexOf("/");
    var relUrl = arrUrl[1].substring(start);

    return relUrl;
}

export function getUrlParam(name, defaultValue) {
    const reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    const searchstr =
        window.location.href.split("%EF%BC%9F")[1] ||
        window.location.href.split("？")[1] ||
        window.location.search.substr(1);
    const searchstrs = searchstr.split("?")[0];
    const r = searchstrs.match(reg);
    if (r != null) {
        return unescape(r[2]);
    }
    return defaultValue || null;
}

export function isEmpty(e) {
    return (
        !e || JSON.stringify(e) === "{}" || (e instanceof Array && !e.length)
    );
}
