"""升級既有站台時，新程式必須在 migration 完成、對外入口關閉的狀態下才接手，最後才重新開放。"""
import sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from operations import Deployment

class RecordingDeployment(Deployment):
    def __init__(self):
        self.calls=[];self.project='isolated'
    def run(self,args,**_):
        self.calls.append(' '.join(args))
        class Result:returncode=0;stdout=b'';stderr=b''
        return Result()
    def running(self,service):return True
    def manifest(self):self.calls.append('manifest');return {}
    def readiness(self,public=False):self.calls.append('readiness-public' if public else 'readiness');return {'generation':'g'}
    def media(self):self.calls.append('media');return {}

class UpgradeOrder(unittest.TestCase):
    def test_migrates_before_reopening_traffic(self):
        deployment=RecordingDeployment();deployment.upgrade()
        calls=deployment.calls
        stop=calls.index('stop web publisher')
        swap=calls.index('up -d --no-deps --no-build app')
        migrate=next(i for i,c in enumerate(calls) if c.endswith('bin/console.php db:migrate'))
        publish=next(i for i,c in enumerate(calls) if c.endswith('bin/console.php content:publish'))
        reopen=calls.index('up -d --no-build publisher web')
        self.assertLess(stop,swap);self.assertLess(swap,migrate);self.assertLess(migrate,publish);self.assertLess(publish,reopen)

if __name__=='__main__':unittest.main()
