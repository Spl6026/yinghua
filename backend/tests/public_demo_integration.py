"""[E-26] 本階段原型 demo 可重跑且保留編輯；只在隔離庫測試固定 demo UUID。"""
import json
from news_public_integration import ROOT,db,run,get

command=['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:seed-public-demo']
checks=[]
def check(name,value):
    if not value:raise AssertionError(name)
    checks.append(name)

def main():
    first=json.loads(run(command).stdout)
    check('demo command clearly identifies nonproduction content','示範' in first['warning'])
    chosen=[('news_posts','05000000-0000-4000-8200-000000000000','title'),('offers','05000000-0000-4000-8300-000000000101','terms')]
    store=db("SELECT id,name,version,updated_at FROM merchants ORDER BY created_at,id LIMIT 1")[0]
    chosen.append(('merchants',store['id'],'name'))
    before=[]
    try:
        for table,record_id,column in chosen:
            row=db(f'SELECT {column},version,updated_at FROM {table} WHERE id=:id',{'id':record_id})[0]
            before.append((table,record_id,column,row))
            db(f'UPDATE {table} SET {column}=:value,version=version+1 WHERE id=:id',{'id':record_id,'value':'本機驗收修改，demo 不得覆寫'})
        result=json.loads(run(command).stdout)
        check('demo rerun preserves all existing content',all(item['action']=='preserved' for item in result['items']))
        for table,record_id,column,row in before:
            after=db(f'SELECT {column},version FROM {table} WHERE id=:id',{'id':record_id})[0]
            check('operator edit retained '+table,after[column]=='本機驗收修改，demo 不得覆寫' and after['version']==row['version']+1)
        for path,target in [('/news-detail.html?id=0','/news/slow-market.html'),('/offer-detail.html?id=101','/offers/or-crafts.html'),('/offer-detail.html?id=0','/offers/or-crafts.html'),('/news/coffee-walk.html','/offers/autumn-coffee.html')]:
            code,_,headers=get(path);check('legacy route '+path,code==301 and headers.get('Location')==target)
        check('offers excluded from city classification','秋日咖啡散步優惠' not in get('/city-news.html')[1])
        print(json.dumps({'checks':len(checks),'restored':True}))
        (ROOT/'artifacts/phase5-demo-results.json').write_text(json.dumps({'checks':len(checks),'cases':checks,'restored':True},ensure_ascii=False,indent=2),encoding='utf8')
    finally:
        for table,record_id,column,row in before:
            db(f'UPDATE {table} SET {column}=:value,version=:version,updated_at=:updated WHERE id=:id',{'id':record_id,'value':row[column],'version':row['version'],'updated':row['updated_at']})
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])

if __name__=='__main__':main()
