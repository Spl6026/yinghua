"""[E-29] Scan immutable runtime images without Docker socket or project-secret mounts."""
import argparse,collections,json,re,subprocess
from pathlib import Path
from operations import Deployment,OperationError,private_target

SCANNER='aquasec/trivy:0.76.1@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c'
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True)
parser.add_argument('--output-directory',required=True);parser.add_argument('--cache-volume',default='spl-trivy-cache')
options=parser.parse_args();deployment=Deployment(options.env_file,options.project);deployment.preflight()
if not re.fullmatch(r'[a-zA-Z0-9][a-zA-Z0-9_.-]+',options.cache_volume):raise OperationError('Invalid scanner cache volume')
work=private_target(options.output_directory)
if work.exists():raise OperationError('Preserve previous scan evidence; choose a new output directory')
inputs=work/'input';output=work/'output';inputs.mkdir(parents=True,mode=0o700);output.mkdir(mode=0o700)
reports=[]
for name in ['app','web','db']:
    image=deployment.config['services'][name]['image'];archive=inputs/(name+'.tar')
    subprocess.run(['docker','image','save','-o',str(archive),image],check=True,capture_output=True)
    image_id=subprocess.check_output(['docker','image','inspect','--format','{{.Id}}',image],text=True).strip()
    command=['docker','run','--rm','--mount','type=bind,src='+str(inputs)+',dst=/input,readonly',
        '--mount','type=bind,src='+str(output)+',dst=/output','--mount','type=volume,src='+options.cache_volume+',dst=/root/.cache',
        SCANNER,'image','--input','/input/'+name+'.tar','--scanners','vuln,secret','--severity','HIGH,CRITICAL',
        '--format','json','--output','/output/'+name+'.json','--exit-code','1','--timeout','10m']
    result=subprocess.run(command,capture_output=True);(output/(name+'.scanner.log')).write_bytes(result.stderr)
    report={'service':name,'imageId':image_id,'exitCode':result.returncode,'counts':{},'fixable':0,'secrets':0}
    if not (output/(name+'.json')).exists():report['executed']=False
    else:
        data=json.loads((output/(name+'.json')).read_text(encoding='utf8'));severity=collections.Counter()
        for target in data.get('Results',[]):
            report['secrets']+=len(target.get('Secrets',[]))
            for item in target.get('Vulnerabilities',[]):
                severity[item['Severity']]+=1;report['fixable']+=bool(item.get('FixedVersion'))
        report['counts']=dict(severity);report['executed']=True
    reports.append(report);print(json.dumps(report),flush=True)
(work/'summary.json').write_text(json.dumps({'scanner':SCANNER,'reports':reports},indent=2),encoding='utf8')
if any(row['exitCode'] for row in reports):raise SystemExit(1)
