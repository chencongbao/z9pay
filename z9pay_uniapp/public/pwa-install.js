(function () {
  var dismissedKey = "luckypay_pwa_install_dismissed_session";

  function isStandalone() {
    return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone;
  }

  function runAfterLoad(callback) {
    if (document.readyState === "complete") {
      callback();
      return;
    }

    window.addEventListener("load", callback);
  }

  function isDismissed() {
    try {
      return window.sessionStorage.getItem(dismissedKey) === "1";
    } catch (error) {
      return false;
    }
  }

  function setDismissed() {
    try {
      window.sessionStorage.setItem(dismissedKey, "1");
    } catch (error) {}
  }

  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;

    runAfterLoad(function () {
      navigator.serviceWorker.register("/sw.js").catch(function (error) {
        console.warn("Service worker registration failed:", error);
      });
    });
  }

  function createPrompt(mode, deferredPrompt, closePrompt, dismissPrompt) {
    var prompt = document.createElement("div");
    prompt.id = "pwa-install-prompt";
    prompt.style.cssText = ["position:fixed", "left:16px", "right:16px", "bottom:calc(16px + env(safe-area-inset-bottom))", "z-index:99999", "display:flex", "align-items:center", "gap:12px", "padding:12px 14px", "box-sizing:border-box", "background:#ffffff", "color:#111827", "border:1px solid rgba(17,24,39,.08)", "border-radius:12px", "box-shadow:0 10px 30px rgba(0,0,0,.18)", 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif'].join(";");

    var text = document.createElement("div");
    text.style.cssText = "flex:1;min-width:0;font-size:14px;line-height:1.45";
    var contentMap = {
      native: '<strong style="display:block;font-size:15px;margin-bottom:2px">添加到主屏幕</strong><span>安装后可从桌面快捷打开 Z9PAY。</span>',
      ios: '<strong style="display:block;font-size:15px;margin-bottom:2px">添加到主屏幕</strong><span>点击 Safari 底部分享按钮，再选择“添加到主屏幕”。</span>',
      guide: '<strong style="display:block;font-size:15px;margin-bottom:2px">添加到主屏幕</strong><span>点击浏览器菜单，选择“安装应用”或“添加到主屏幕”。</span>',
    };
    text.innerHTML = contentMap[mode] || contentMap.guide;

    var action = document.createElement("button");
    action.type = "button";
    action.textContent = mode === "native" ? "添加" : "知道了";
    action.style.cssText = ["flex:0 0 auto", "height:34px", "padding:0 14px", "border:0", "border-radius:8px", "background:#2563eb", "color:#ffffff", "font-size:14px", "font-weight:600"].join(";");

    var close = document.createElement("button");
    close.type = "button";
    close.setAttribute("aria-label", "关闭");
    close.textContent = "x";
    close.style.cssText = ["flex:0 0 auto", "width:28px", "height:28px", "border:0", "background:transparent", "color:#6b7280", "font-size:22px", "line-height:28px"].join(";");

    action.addEventListener("click", function () {
      if (mode !== "native" || !deferredPrompt.value) {
        dismissPrompt();
        return;
      }

      deferredPrompt.value.prompt();
      deferredPrompt.value.userChoice.finally(function () {
        deferredPrompt.value = null;
        closePrompt();
      });
    });
    close.addEventListener("click", dismissPrompt);

    prompt.appendChild(text);
    prompt.appendChild(action);
    prompt.appendChild(close);

    return prompt;
  }

  function setupInstallPrompt() {
    if (isStandalone() || isDismissed()) return;

    var deferredPrompt = { value: null };
    var isShowing = false;
    var ua = window.navigator.userAgent.toLowerCase();
    var isIos = /iphone|ipad|ipod/.test(ua);

    function closePrompt() {
      var prompt = document.getElementById("pwa-install-prompt");
      if (prompt) prompt.remove();
      isShowing = false;
    }

    function dismissPrompt() {
      setDismissed();
      closePrompt();
    }

    function showPrompt(mode) {
      if (isShowing || document.getElementById("pwa-install-prompt")) return;
      isShowing = true;
      document.body.appendChild(createPrompt(mode, deferredPrompt, closePrompt, dismissPrompt));
    }

    window.addEventListener("beforeinstallprompt", function (event) {
      event.preventDefault();
      deferredPrompt.value = event;
      closePrompt();
      setTimeout(function () {
        showPrompt("native");
      }, 1200);
    });

    window.addEventListener("appinstalled", function () {
      setDismissed();
      closePrompt();
    });

    if (isIos) {
      runAfterLoad(function () {
        setTimeout(function () {
          showPrompt("ios");
        }, 1800);
      });
      return;
    }

    runAfterLoad(function () {
      setTimeout(function () {
        showPrompt(deferredPrompt.value ? "native" : "guide");
      }, 2200);
    });
  }

  if (!window || !document) return;
  registerServiceWorker();
  setupInstallPrompt();
})();
