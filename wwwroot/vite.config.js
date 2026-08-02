import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { createVuePlugin } from "vite-plugin-vue2";
import legacy from "@vitejs/plugin-legacy";
import EnvCompatiblePlugin from 'vite-plugin-env-compatible';
import path from "path";

export default defineConfig({
    plugins: [
        createVuePlugin(),
        legacy({
            targets: ['Android >= 5', 'Chrome >= 49'],
            modernPolyfills: true,
        }),
        laravel({
            input: {
                "cashier":"resources/js/app.js",
                "luckypay":"resources/js/app-luckypay.js",
                "apluspay":"resources/js/app-apluspay.js",
                "sgpay":"resources/js/app-sgpay.js",
                "shpay":"resources/js/app-shpay.js",
                "haoyunlai":"resources/js/app-haoyunlai.js",
                "lupay":"resources/js/app-lupay.js",
                "lixiangpay":"resources/js/app-lixiangpay.js",
                "gold":"resources/js/gold.js",
                "oro7pay":"resources/js/app-oro7pay.js",
                "rdspay":"resources/js/app-rdspay.js",
                "infinitepay":"resources/js/app-infinitepay.js",
                "phpay":"resources/js/app-phpay.js",
                "thuyphatpay":"resources/js/app-thuyphatpay.js",
                "nnpay":"resources/js/app-nnpay.js",
                "huiqianjinpay":"resources/js/app-huiqianjinpay.js",
                "apay":"resources/js/app-apay.js",
                "tp88pay":"resources/js/app-tp88pay.js",
                "z9pay":"resources/js/app-z9pay.js"
            },
            refresh: true,
        }),
        EnvCompatiblePlugin({
            prefix: 'VITE_'
        })
    ],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources"),
            "~": path.resolve(__dirname, "resources"),
        },
    },
    css: {
        preprocessorOptions: {
            less: {
                javascriptEnabled: true,
            },
        }
    },
});
