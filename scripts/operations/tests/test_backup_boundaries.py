"""[E-29] Damaged metadata and tar traversal must fail before any database operation."""
import io,json,sys,tarfile,tempfile,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from operations import OperationError,hash_file,verify_backup

class BackupBoundaries(unittest.TestCase):
    def test_non_object_metadata_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            target=Path(directory);(target/'manifest.json').write_text('null',encoding='utf8')
            with self.assertRaises(OperationError):verify_backup(target)

    def test_traversal_and_symlink_archives_are_rejected(self):
        for link in [False,True]:
            with self.subTest(link=link),tempfile.TemporaryDirectory() as directory:
                target=Path(directory);(target/'database.sql').write_text('-- isolated fixture',encoding='utf8')
                (target/'release.json').write_text(json.dumps({'format':1,'version':'test','commit':'a'*40,'migrations':{'001_test.sql':'a'*64}}),encoding='utf8')
                with tarfile.open(target/'media.tar','w') as archive:
                    entry=tarfile.TarInfo('a'*36+'.png' if link else '../escape.png')
                    if link:entry.type=tarfile.SYMTYPE;entry.linkname='/private'
                    else:entry.size=1
                    archive.addfile(entry,None if link else io.BytesIO(b'x'))
                (target/'manifest.json').write_text(json.dumps({'format':1,'sourceProject':'isolated','appKeyFingerprint':'b'*64,'media':{},
                    'files':{name:hash_file(target/name) for name in ['database.sql','media.tar','release.json']}}),encoding='utf8')
                with self.assertRaises(OperationError):verify_backup(target)

if __name__=='__main__':unittest.main()
