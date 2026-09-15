import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
const root=path.dirname(new URL(import.meta.url).pathname), executable=process.env.RAJEH_CHROMIUM;
const browser=await chromium.launch({headless:true,executablePath:executable,args:['--no-sandbox']});
const results=[];
for(const locale of ['ar','en']) for(const view of ['setup','products','pricing','affected']) for(const device of [{name:'desktop',width:1366,height:900},{name:'mobile',width:390,height:844}]){
 const page=await browser.newPage({viewport:{width:device.width,height:device.height}}); await page.goto(`file://${root}/fixture.html?locale=${locale}&view=${view}`); const check=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,mixed:document.documentElement.lang==='ar'?/[A-Za-z]{4,}/.test(document.body.innerText.replace(/TEST-[A-Z0-9-]+/g,'')):/[\u0600-\u06ff]/.test(document.body.innerText),raw:/\$[A-Za-z_]|awaiting_distribution|__[.(]/.test(document.body.innerText)})); await page.screenshot({path:path.join(root,`${locale}-${view}-${device.name}.png`),fullPage:true}); results.push({locale,view,device:device.name,...check}); await page.close();
}
await browser.close(); fs.writeFileSync(path.join(root,'results.json'),JSON.stringify(results,null,2)); if(results.some(x=>x.overflow||x.mixed||x.raw)) process.exit(1);
