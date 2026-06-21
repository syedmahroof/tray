const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://tray.test/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);
  await page.goto('http://tray.test/projects/create');
  await page.waitForTimeout(1000);
  await page.click('button:has-text("Select a category")');
  await page.waitForTimeout(500);
  await page.screenshot({ path: '/tmp/verify-shots/dbg-combobox-open.png', fullPage: true });
  const html = await page.content();
  require('fs').writeFileSync('/tmp/verify-shots/combobox.html', html);
  await browser.close();
})();
