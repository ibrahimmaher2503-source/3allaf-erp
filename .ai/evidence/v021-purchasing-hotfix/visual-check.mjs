import { chromium } from 'playwright';
import fs from 'node:fs';
const root=new URL('.',import.meta.url),browser=await chromium.launch({headless:true,executablePath:'/home/rajeh_ahmed/.cache/rajeh-v021-hotfix-chromium/chrome-headless-shell'}),results=[];
for(const locale of ['ar','en'])for(const screen of ['list','create'])for(const viewport of [{name:'desktop',width:1440,height:1000},{name:'mobile',width:390,height:844}]){
 const page=await browser.newPage({viewport:{width:viewport.width,height:viewport.height}}),url=new URL(`fixture.html?locale=${locale}&screen=${screen}`,root).href;await page.goto(url);await page.evaluate(()=>document.fonts.ready);
 const metrics=await page.evaluate(({locale})=>{const body=document.body,copy=[...document.querySelectorAll('.ui-copy')].map(e=>e.textContent).join(' '),raw=body.innerText.includes('awaiting_distribution'),overflow=body.scrollWidth>innerWidth,clipped=[...document.querySelectorAll('.card,.button,.input')].filter(e=>{const r=e.getBoundingClientRect();return r.right>innerWidth+1||r.left< -1}).length,mixed=locale==='en'?/\p{Script=Arabic}/u.test(copy):/\b(?:Purchase|Invoice|Stage|Review|Add|Scan|Search|Product|Store|Date|Total|Print|Approved|Awaiting)\b/.test(copy);return{overflow,clipped,raw,mixed,scrollWidth:body.scrollWidth,viewport:innerWidth}}, {locale});
 const name=`${locale}-${screen}-${viewport.name}.png`;await page.screenshot({path:new URL(name,root).pathname,fullPage:true});results.push({locale,screen,viewport:viewport.name,...metrics,screenshot:name});await page.close();
}
await browser.close();fs.writeFileSync(new URL('results.json',root),JSON.stringify({result:results.every(r=>!r.overflow&&!r.clipped&&!r.raw&&!r.mixed)?'passed':'failed',cases:results},null,2));
if(results.some(r=>r.overflow||r.clipped||r.raw||r.mixed))process.exit(1);
