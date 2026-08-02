const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

async function main() {
  const [, , htmlPath, imagePath] = process.argv;

  if (!htmlPath || !imagePath) {
    throw new Error('Usage: node render-transfer-receipt.js <htmlPath> <imagePath>');
  }

  if (!fs.existsSync(htmlPath)) {
    throw new Error(`HTML file not found: ${htmlPath}`);
  }

  fs.mkdirSync(path.dirname(imagePath), { recursive: true });

  const launchOptions = {
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  };

  if (process.env.TRANSFER_RECEIPT_CHROME_BINARY) {
    launchOptions.executablePath = process.env.TRANSFER_RECEIPT_CHROME_BINARY;
  }

  const browser = await chromium.launch(launchOptions);

  try {
    const page = await browser.newPage({
      viewport: { width: 430, height: 760 },
      deviceScaleFactor: 2,
    });

    await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });
    const receipt = page.locator('.receipt-page');
    await receipt.screenshot({ path: imagePath, type: 'png' });
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error.message || error);
  process.exit(1);
});
