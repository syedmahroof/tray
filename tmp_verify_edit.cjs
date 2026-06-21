const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://tray.test/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);
  await page.goto('http://tray.test/projects/2/edit');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: '/tmp/verify-shots/08-edit-prefilled.png', fullPage: true });
  await browser.close();
})();
