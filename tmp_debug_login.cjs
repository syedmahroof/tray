const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://tr.test/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  console.log('AFTER_LOGIN_URL=' + page.url());
  await page.screenshot({ path: '/tmp/verify-shots/debug-after-login.png', fullPage: true });
  await browser.close();
})();
