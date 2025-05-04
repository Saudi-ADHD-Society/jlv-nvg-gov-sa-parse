// scrape-nvg.js
const puppeteer = require('puppeteer');
const fs = require('fs');

const TARGET_URL = 'https://nvg.gov.sa/public/ent-prov/detail/75a61b91-a86a-4a4b-9e06-b119696d0f44';

(async () => {
//  const browser = await puppeteer.launch({ headless: true });

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox']
  });

  const page = await browser.newPage();

  await page.goto(TARGET_URL, { waitUntil: 'networkidle0' });

  // Wait for cards to appear
  await page.waitForSelector('.card');

  const opportunities = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('.card-body'));
    return cards.map(card => {
      const ps = Array.from(card.querySelectorAll('p')).map(p => p.innerText.trim());
      const link = card.querySelector('a.join_btn')?.href || '';

      return {
        title: ps[0] || '',
        location: ps[1] || '',
        description: ps[2] || '',
        daysLeft: ps[3] || '',
        dates: ps[4] || '',
        seats: ps[5] || '',
        link: link.startsWith('/') ? `https://nvg.gov.sa${link}` : link
      };
    });
  });

  fs.writeFileSync('nvg-opportunities.json', JSON.stringify(opportunities, null, 2));
  console.log('✔ Done: Saved to nvg-opportunities.json');

  // Also dump the full rendered page for inspection
  const renderedHtml = await page.content();
  fs.writeFileSync('rendered.html', renderedHtml);
  console.log('🕵️ Saved full page HTML to rendered.html');

  await browser.close();
})();
