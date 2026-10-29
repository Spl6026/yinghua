import subprocess,sys,unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from operations import Deployment,OperationError

class SafeOperationErrors(unittest.TestCase):
    def deployment(self):
        deployment=Deployment.__new__(Deployment);deployment.command=['docker','compose'];deployment.config={'services':{'app':{'environment':{'APP_KEY':'private-key-value','SMTP_USERNAME':'private@example.test'}}}}
        return deployment

    def test_lifecycle_failure_retains_reason_without_environment_secrets(self):
        result=subprocess.CompletedProcess([],1,b'',b'container startup failed: private-key-value private@example.test')
        with patch('operations.subprocess.run',return_value=result),self.assertRaises(OperationError) as caught:self.deployment().run(['start','app'])
        message=str(caught.exception);self.assertIn('container startup failed',message);self.assertNotIn('private-key-value',message);self.assertNotIn('private@example.test',message)

    def test_sql_or_application_output_is_never_copied_into_operator_error(self):
        result=subprocess.CompletedProcess([],1,b'',b'data from failed SQL: member@example.test')
        with patch('operations.subprocess.run',return_value=result),self.assertRaises(OperationError) as caught:self.deployment().run(['exec','-T','db'])
        self.assertNotIn('member@example.test',str(caught.exception))

if __name__=='__main__':unittest.main()
