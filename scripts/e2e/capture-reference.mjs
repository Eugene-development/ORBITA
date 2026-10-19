import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
await mkdir('../runtime/reference',{recursive:true});
const browser=await chromium.launch({channel:'chrome'});
for(const width of [360,390,768,1024,1440]) {
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});
  for(const [label,path] of [['home','/'],['contact','/contact']]) {
    await page.goto(`https://xn----7sbc2ahzelejid.xn--p1ai${path}`,{waitUntil:'domcontentloaded'});
    await page.locator('h1,h2').first().waitFor();
    await page.evaluate(async()=>{
      for(const image of document.images) image.loading='eager';
      await Promise.all([...document.images].map(image=>image.decode().catch(()=>{})));
    });
    await page.screenshot({path:`../runtime/reference/source-${label}-${width}.png`,fullPage:true});
    await page.screenshot({path:`../runtime/reference/source-${label}-${width}-viewport.png`});
  }
  await page.close();
}
await browser.close();
console.log('Source captures saved at matching desktop/mobile viewports. No forms submitted.');
