// src/utils/override-loader.js
const ALL = import.meta.glob('../components/**/*.vue'); // 直接绝对路径，避免 @ 别名问题

// 拼路径
function p(...segs) {
    return `../components/${segs.filter(Boolean).join('/')}`;
}

/**
 * 生成候选路径（currency → code → system）
 * 支持：
 *  1. cashier/{currency}/{code}/{system}/{Name}.vue
 *  2. cashier/{currency}/{code}/_default/{Name}.vue
 *  3. cashier/{currency}/default/_default/{Name}.vue
 *  4. {Name}.vue（根目录默认）
 */
function candidates(relPath, { currency = 'default', code = 'default', system = 'default' }) {
    const N = relPath.endsWith('.vue') ? relPath : `${relPath}.vue`;

    return [
        // ① 完整路径
        p('cashier', currency, code, system, N),

        // ② code 下默认 system
        p('cashier', currency, code, '_default', N),

        // ③ currency 默认 system
        p('cashier', currency, 'default', system, N),

        // ③ currency 默认 code/system
        p('cashier', currency, 'default', '_default', N),

        // ④ 根目录默认
        p(N),
    ];
}

/**
 * 异步组件加载器（Vue2 兼容）
 */
export function resolveComponent(relPath, ctx) {
    const list = candidates(relPath, ctx);
    for (const key of list) {
        if (ALL[key]) {
            //console.log(`[componentsRegister] matched: ${relPath} -> ${key}`, ctx);
            return () => ALL[key]().then((m) => m.default || m);
        }
    }
    return () =>
        Promise.resolve({
            render(h) {
                return h('div', `Component not found: ${relPath}`);
            },
        });
}

/**
 * 全局注册多个覆盖组件
 */
export function registerOverrides(Vue, mapping, ctx) {
    Object.entries(mapping).forEach(([name, relPath]) => {
        Vue.component(name, resolveComponent(relPath, ctx));
    });
}
