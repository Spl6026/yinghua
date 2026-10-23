"""[E-27] Repeated public-version changes must not leak drafts into private resources."""
import json,uuid
import member_resources_integration as probe
from api_integration import ROOT,fixture

def main():
    member_id=None;place=str(uuid.uuid4());base='/api/v1/me/itineraries/current';favorite='/api/v1/me/favorites'
    try:
        fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('{place}','{probe.prefix}-stability','公開版本 0','景點','','','','published','2020-01-01')")
        probe.publish();client,member_id,_=probe.member('publication')
        probe.check('initial favorite saved',probe.call(client,'PUT',favorite+'/'+place)[0]==201)
        probe.check('initial trip saved',probe.call(client,'PUT',base+'/items/'+place,{'version':0})[0]==201)
        for version in range(1,6):
            fixture(f"UPDATE attractions SET name='公開版本 {version}',version=version+1 WHERE id='{place}'")
            pending=probe.call(client,'GET',favorite)[1]['items'][0]
            probe.check('unpublished version remains unknown '+str(version),pending['availability']=='unknown' and pending['attraction'] is None)
            probe.publish()
            public=client.call('GET','/api/v1/attractions/'+probe.prefix+'-stability')
            probe.check('published API ready '+str(version),public[0]==200)
            saved=probe.call(client,'GET',favorite)[1]['items'][0]
            trip=probe.call(client,'GET',base)[1]['itinerary']['items'][0]
            probe.check('favorite uses current public version '+str(version),saved['availability']=='available' and saved['attraction']['name']=='公開版本 '+str(version))
            probe.check('trip uses public view and retains original snapshot '+str(version),trip['attraction']['name']==saved['attraction']['name'] and trip['snapshotName']=='公開版本 0')
            probe.check('remove favorite '+str(version),probe.call(client,'DELETE',favorite+'/'+place)[0]==204)
            response=probe.call(client,'PUT',favorite+'/'+place)
            probe.check('re-add only to successfully published target '+str(version)+' '+str(response[0])+'/'+str(response[1].get('code')),response[0]==201)
        report={'checks':len(probe.checks),'contractResponses':probe.responses,'publicationRounds':5,'csrfRecoveries':probe.csrf_recoveries,'fixturesRemoved':True}
    finally:
        if member_id:
            fixture(f"DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id='{member_id}'")
            fixture(f"DELETE FROM itineraries WHERE member_id='{member_id}'")
            fixture(f"DELETE FROM favorites WHERE member_id='{member_id}'")
            fixture(f"DELETE FROM sessions WHERE member_id='{member_id}'")
            fixture(f"UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='{member_id}'")
        fixture(f"DELETE FROM attractions WHERE id='{place}'");probe.publish()
    (ROOT/'artifacts/phase6-publication-results.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))

if __name__=='__main__':main()
