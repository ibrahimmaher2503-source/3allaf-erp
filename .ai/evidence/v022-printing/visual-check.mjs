import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
const root=path.dirname(new URL(import.meta.url).pathname);
const browser=await chromium.launch({headless:true,executablePath:process.env.RAJEH_CHROMIUM,args:['--no-sandbox']});
const results=[];
for(const locale of ['ar','en']) for(const view of ['library','printer']) for(const device of [{name:'desktop',width:1366,height:900},{name:'mobile',width:390,height:844}]){
 const page=await browser.newPage({viewport:{width:device.width,height:device.height}});
 await page.goto(`file://${root}/fixture.html?locale=${locale}&view=${view}`);
 const check=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,mixed:document.documentElement.lang==='ar'?/\b(?:Printing|Create|Arabic|English|Document|Paper|Language|Status|Active|Save|Preview|Printer|Branch|Store|Thermal|Test)\b/.test(document.body.innerText):/[\u0600-\u06ff]/.test(document.body.innerText),minTargets:[...document.querySelectorAll('a,input,select')].every(e=>e.getBoundingClientRect().height>=44),direction:document.documentElement.dir}));
 const screenshot=`${locale}-${view}-${device.name}.png`; await page.screenshot({path:path.join(root,screenshot),fullPage:true}); results.push({locale,view,device:device.name,screenshot,...check}); await page.close();
}
await browser.close(); fs.writeFileSync(path.join(root,'results.json'),JSON.stringify({result:results.every(x=>!x.overflow&&!x.mixed&&x.minTargets)?'passed':'failed',cases:results},null,2)); if(results.some(x=>x.overflow||x.mixed||!x.minTargets))process.exit(1);
