const TransformPages = require("uni-read-pages");
const { webpack } = new TransformPages();
const fs = require("fs");
const path = require("path");

class PwaBuildPlugin {
  apply(compiler) {
    compiler.hooks.afterEmit.tap("PwaBuildPlugin", () => {
      const sourceDir = path.resolve(__dirname, "public");
      const outputDir = compiler.options.output && compiler.options.output.path;

      if (!outputDir || !fs.existsSync(sourceDir)) return;
      this.copyDir(sourceDir, outputDir);
      this.injectIndexHtml(path.join(outputDir, "index.html"));
    });
  }

  copyDir(sourceDir, outputDir) {
    fs.mkdirSync(outputDir, { recursive: true });
    fs.readdirSync(sourceDir, { withFileTypes: true }).forEach((entry) => {
      const sourcePath = path.join(sourceDir, entry.name);
      const outputPath = path.join(outputDir, entry.name);

      if (entry.isDirectory()) {
        this.copyDir(sourcePath, outputPath);
        return;
      }

      fs.copyFileSync(sourcePath, outputPath);
    });
  }

  injectIndexHtml(indexPath) {
    if (!fs.existsSync(indexPath)) return;

    let html = fs.readFileSync(indexPath, "utf8");
    const headTags = [
      { test: "name=theme-color", tag: '<meta name="theme-color" content="#2563eb">' },
      { test: "apple-mobile-web-app-capable", tag: '<meta name="apple-mobile-web-app-capable" content="yes">' },
      { test: "apple-mobile-web-app-status-bar-style", tag: '<meta name="apple-mobile-web-app-status-bar-style" content="default">' },
      { test: "apple-mobile-web-app-title", tag: '<meta name="apple-mobile-web-app-title" content="Z9PAY">' },
      { test: "rel=manifest", tag: '<link rel="manifest" href="/manifest.json">' },
      { test: "apple-touch-icon", tag: '<link rel="apple-touch-icon" href="/icons/icon-192.png">' },
    ]
      .filter((item) => !html.includes(item.test) && !html.includes(item.tag))
      .map((item) => item.tag)
      .join("");

    if (headTags) {
      html = html.replace("</head>", `${headTags}</head>`);
    }

    if (!html.includes("/pwa-install.js")) {
      html = html.replace("</body>", '<script src="/pwa-install.js"></script></body>');
    }

    fs.writeFileSync(indexPath, html);
  }
}

module.exports = {
  transpileDependencies: ["uni-ajax"],
  configureWebpack: {
    plugins: [
      new PwaBuildPlugin(),
      new webpack.DefinePlugin({
        ROUTES: webpack.DefinePlugin.runtimeValue(() => {
          const tfPages = new TransformPages({
            includes: ["path", "name", "aliasPath", "meta"],
          });
          return JSON.stringify(tfPages.routes);
        }, true),
      }),
    ],
  },
};
