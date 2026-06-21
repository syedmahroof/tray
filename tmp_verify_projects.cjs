const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', (err) => errors.push(String(err)));

  // Login
  await page.goto('http://tray.test/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  console.log('AFTER_LOGIN_URL=' + page.url());
  await page.screenshot({ path: '/tmp/verify-shots/00-after-login.png', fullPage: true });

  // Go to create project page
  await page.goto('http://tray.test/projects/create');
  await page.waitForTimeout(1500);
  console.log('CREATE_PAGE_URL=' + page.url());
  await page.screenshot({ path: '/tmp/verify-shots/01-create-empty.png', fullPage: true });

  // Fill basics
  await page.fill('#name', 'Verify Skyline Towers');
  // Project type combobox
  await page.click('button:has-text("Select a category")');
  await page.locator('[data-state="open"] [role="option"]').first().click();
  // Builder combobox
  await page.click('button:has-text("Select a builder")');
  await page.locator('[data-state="open"] [role="option"]').first().click();
  // Branch combobox (required for Super Admin)
  await page.click('button:has-text("Select a branch")');
  await page.locator('[data-state="open"] [role="option"]').first().click();

  // Team Contacts: open combobox, check options show type
  await page.click('button:has-text("Select contact...")');
  await page.waitForSelector('[data-state="open"] [role="option"]');
  const optionTexts = await page.locator('[data-state="open"] [role="option"]').allTextContents();
  console.log('TEAM_CONTACT_OPTIONS=' + JSON.stringify(optionTexts));
  await page.screenshot({ path: '/tmp/verify-shots/02-team-contacts-options.png' });
  await page.locator('[data-state="open"] [role="option"]').first().click();
  await page.locator('div.flex.gap-2 > button[type="button"]').click();

  await page.screenshot({ path: '/tmp/verify-shots/03-team-contact-added.png', fullPage: true });

  // Project Contacts: add two rows
  await page.click('button:has-text("Add project contact")');
  await page.click('button:has-text("Add project contact")');
  await page.screenshot({ path: '/tmp/verify-shots/04-project-contacts-two-rows.png', fullPage: true });

  const nameInputs = await page.$$('input[name^="project_contacts"][name$="[name]"]');
  console.log('PROJECT_CONTACT_ROWS=' + nameInputs.length);
  await nameInputs[0].fill('Ramesh Kumar');
  await page.fill('input[name="project_contacts[0][role]"]', 'Site Engineer');
  await page.fill('input[name="project_contacts[0][phone]"]', '9000000001');
  await page.fill('input[name="project_contacts[0][email]"]', 'ramesh@example.com');

  await nameInputs[1].fill('Suresh Babu');
  await page.fill('input[name="project_contacts[1][role]"]', 'Supervisor');
  await page.fill('input[name="project_contacts[1][phone]"]', '9000000002');
  await page.fill('input[name="project_contacts[1][email]"]', 'suresh@example.com');

  await page.screenshot({ path: '/tmp/verify-shots/05-project-contacts-filled.png', fullPage: true });

  // Remove the second row, verify reindexing
  const removeButtons = await page.$$('div.rounded-lg.border.p-4 button:has(svg)');
  await removeButtons[removeButtons.length - 1].click();
  const remainingNameInputs = await page.$$('input[name^="project_contacts"][name$="[name]"]');
  console.log('AFTER_REMOVE_ROWS=' + remainingNameInputs.length);
  const remainingNames = [];
  for (const el of remainingNameInputs) remainingNames.push(await el.inputValue());
  console.log('AFTER_REMOVE_NAMES=' + JSON.stringify(remainingNames));

  await page.screenshot({ path: '/tmp/verify-shots/06-project-contacts-after-remove.png', fullPage: true });

  console.log('CONSOLE_ERRORS=' + JSON.stringify(errors));

  // Submit
  page.on('response', (res) => {
    if (res.request().method() === 'POST') {
      console.log('POST_RESPONSE=' + res.status() + ' ' + res.url());
    }
  });
  await page.click('button:has-text("Create project")');
  await page.waitForTimeout(2000);
  await page.screenshot({ path: '/tmp/verify-shots/07-after-submit.png', fullPage: true });
  console.log('URL_AFTER_SUBMIT=' + page.url());

  await browser.close();
})();
