// env/index.js
let env = process.env.NODE_ENV;

// uniapp 构建环境变量可能是 'development' 或 'production'
let config = {};

if (env === 'development') {
    config = require('./dev.js');
} else {
    config = require('./prod.js');
}

// 导出给全局使用
module.exports = config;