// src/components/factories/cashierRegistry.js

// 自动抓取所有 TrueName.vue / Cashier.vue（Vite）
const files = import.meta.glob('../components/cashier/**/{TrueName,Cashier}.vue');

// 解析路径：支持三层和两层
// 三层：../cashier/<CURRENCY>/<code>/<variant>/<Comp>.vue
// 两层：../cashier/<code>/<variant>/<Comp>.vue（兼容旧）
function parsePath(path) {
    const m3 = path.match(/cashier\/([^/]+)\/([^/]+)\/([^/]+)\/(TrueName|Cashier)\.vue$/);
    if (m3) {
        const [, currency, code, variant, type] = m3;
        return {
            currency: currency.toUpperCase(),
            code: (code || 'default').toLowerCase(),
            variant: normVariant(variant),
            type,
            withCurrency: true,
        };
    }
    const m2 = path.match(/cashier\/([^/]+)\/([^/]+)\/(TrueName|Cashier)\.vue$/);
    if (m2) {
        const [, code, variant, type] = m2;
        return {
            currency: null,
            code: (code || 'default').toLowerCase(),
            variant: normVariant(variant),
            type,
            withCurrency: false,
        };
    }
    return null;
}

function normVariant(v) {
    const vv = String(v || '_default').toLowerCase();
    return vv === 'default' ? '_default' : vv; // 兼容 default / _default
}

// Vite 动态导入 → Vue2 异步组件工厂
const toVue2Async = (loader) => () => loader().then(m => m.default || m);

// 两套索引
const withCur = {}; // withCur[CURRENCY][code][variant][type] = loader
const legacy  = {}; // legacy[code][variant][type] = loader

Object.entries(files).forEach(([path, loader]) => {
    const i = parsePath(path);
    if (!i) return;
    const { currency, code, variant, type, withCurrency } = i;
    const item = { loader, path };
    if (withCurrency) {
        withCur[currency] ??= {};
        withCur[currency][code] ??= {};
        withCur[currency][code][variant] ??= {};
        withCur[currency][code][variant][type] = item;
    } else {
        legacy[code] ??= {};
        legacy[code][variant] ??= {};
        legacy[code][variant][type] = item;
    }
});

// 取指定 map 的某个变体
const pickVariant = (map, v) => (map ? (map[v] || map['_default'] || map['default'] || null) : null);

// ⭐ 按组件维度回退（TrueName/Cashier各自独立挑选）
export function resolveCashierImpl({ currency, code, system }) {
    const C = String(currency || '').toUpperCase();         // e.g. CNY / USD
    const c = String(code || 'default').toLowerCase();      // e.g. bank / weixin
    const s = String(system || '_default').toLowerCase();   // e.g. ios / android / h5 / _default

    const curMap       = withCur[C];            // 该币种所有
    const curCodeMap   = curMap?.[c];           // 币种下该 code
    const curDefCode   = curMap?.['default'];   // 币种下 default
    const legCodeMap   = legacy[c];             // 旧路径该 code
    const globalDefMap = legacy['default'];     // 全局 default

    // 候选列表（从高到低）——返回某个“变体对象”，后面会按组件 type 取字段
    const variants = [
        pickVariant(curCodeMap, s),         // C + code + system
        pickVariant(curCodeMap, '_default'),
        pickVariant(curDefCode, s),         // C + default + system
        pickVariant(curDefCode, '_default'),
        pickVariant(legCodeMap, s),         // legacy code + system
        pickVariant(legCodeMap, '_default'),
        pickVariant(globalDefMap, '_default'), // global default
    ].filter(Boolean);

    // 按组件维度选 loader：第一个有 TrueName/Cashier 的就用
    const pickMatch = (type) => {
        for (const v of variants) {
            if (v && v[type]) return v[type];
        }
        return null;
    };

    const TrueNameMatch = pickMatch('TrueName');
    const CashierMatch  = pickMatch('Cashier');

    if (TrueNameMatch) {
        console.log(`[cashierRegistry] matched: TrueName -> ${TrueNameMatch.path}`, { currency: C, code: c, system: s });
    }

    if (CashierMatch) {
        console.log(`[cashierRegistry] matched: Cashier -> ${CashierMatch.path}`, { currency: C, code: c, system: s });
    }

    if (!TrueNameMatch || !CashierMatch) {
        console.warn('[cashierRegistry] TrueName/Cashier 缺失，请确认 cashier/default/_default/* 存在');
    }

    return {
        TrueName: TrueNameMatch ? toVue2Async(TrueNameMatch.loader) : undefined,
        Cashier:  CashierMatch  ? toVue2Async(CashierMatch.loader)  : undefined,
    };
}
