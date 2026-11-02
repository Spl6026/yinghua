"""透過既有的瀏覽器 CSRF 策略重跑收藏與目前行程的回歸測試，每個測試組只跑一次。"""
import contextlib,importlib,io,json
import phase6_regression as policy

def main():
    completed=[];observed=[]
    def observed_call(self,method,path,body=None,csrf=True,extra=None):
        response=policy.browser_call(self,method,path,body,csrf,extra)
        if response[0]>=400:observed.append({'method':method,'path':path,'status':response[0],'code':response[1].get('code')})
        return response
    policy.Client.call=observed_call
    try:
        for name in ['member_resources_integration','member_itinerary_integration','member_publication_integration']:
            output=io.StringIO()
            try:
                with contextlib.redirect_stdout(output):importlib.import_module(name).main()
            finally:(policy.ROOT/('artifacts/phase7-'+name+'.log')).write_text(output.getvalue(),encoding='utf8')
            completed.append(name);print(json.dumps({'suite':name,'completed':len(completed)}),flush=True)
    finally:
        policy.Client.call=policy.original
        (policy.ROOT/'artifacts/phase7-member-regression.json').write_text(json.dumps({'completed':completed,'csrfRefreshes':policy.recoveries,'rejections':observed,'allCompleted':len(completed)==3},indent=2),encoding='utf8')

if __name__=='__main__':main()
