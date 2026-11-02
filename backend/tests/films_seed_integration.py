"""受控的電影匯入不推測日期、不覆寫已編輯的內容；原型的四個網址與舊的 0 號網址仍可讀取。"""
import json
from api_integration import Client,ROOT,fixture,password
from attractions_integration import cli,raw

def main():
    fixture(password,'root');root=Client('admin');guest=Client();assert root.login('admin')[0]==200
    checks=[]
    def check(label,value):
        assert value,label
        checks.append(label)
    cli('film:seed-demo')
    data=fixture('SELECT id,prototype_id,slug,title FROM films WHERE prototype_id BETWEEN 0 AND 3 ORDER BY prototype_id')
    check('four stable original metadata records',len(data)==4 and [int(row['prototype_id']) for row in data]==[0,1,2,3])
    for row in data:check('original URL '+row['slug'],raw(guest,'/films/'+row['slug']+'.html')[0]==200)
    check('prototype id zero resolves',raw(guest,'/film-detail.html?id=0')[0]==200)
    first=root.call('GET','/api/v1/admin/films/'+data[0]['id'])[1]['film'];changed=None
    try:
        code,result,_=root.call('PUT','/api/v1/admin/films/'+first['id'],{'title':'已編寫的電影介紹驗收','version':first['version']})
        assert code==200;changed=result['film']
        before=fixture('SELECT id,prototype_id,title,version FROM films WHERE prototype_id BETWEEN 0 AND 3 ORDER BY prototype_id')
        cli('film:seed-demo')
        after=fixture('SELECT id,prototype_id,title,version FROM films WHERE prototype_id BETWEEN 0 AND 3 ORDER BY prototype_id')
        check('repeat seed leaves edits and versions unchanged',before==after)
        check('metadata seed invents no screening years',all(root.call('GET','/api/v1/admin/films/'+row['id'])[1]['film']['screenings']==[] for row in data))
    finally:
        if changed:assert root.call('PUT','/api/v1/admin/films/'+first['id'],{'title':first['title'],'version':changed['version']})[0]==200
    report={'filmSeedChecks':len(checks),'checks':checks}
    (ROOT/'artifacts/phase4-seed-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':main()
