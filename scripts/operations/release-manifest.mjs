// 在同一個 build stage 產生 app 與 web 共用的發布清單；不讀取 .env，也不把秘密寫進映像。
import {readFile,writeFile,readdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {join} from 'node:path';
const [version,commit]=process.argv.slice(2);
if(!/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/.test(version||'')||!/^[a-f0-9]{40}$/.test(commit||''))throw new Error('Public release label and full commit required');
async function hashes(root,path=''){
  const result={};
  for(const entry of (await readdir(join(root,path),{withFileTypes:true})).sort((a,b)=>a.name.localeCompare(b.name))){
    const name=path?path+'/'+entry.name:entry.name;
    if(entry.isDirectory())Object.assign(result,await hashes(root,name));
    else if(entry.isFile())result[name]=createHash('sha256').update(await readFile(join(root,name))).digest('hex');
    else throw new Error('Unexpected release input');
  }
  return result;
}
await writeFile('/release/release.json',JSON.stringify({format:1,version,commit,
  origin:process.env.APP_URL.replace(/\/+$/,''),policyVersion:process.env.TERMS_VERSION,
  migrations:await hashes('/release/migrations'),backend:await hashes('/release/backend'),
  assets:await hashes('/release/frontend/dist')},null,2)+'\n');
