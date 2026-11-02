"""Build a clean deployment archive without credentials, uploads or sessions."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
ROOT=Path(__file__).resolve().parent.parent
out=ROOT/'dist';out.mkdir(exist_ok=True)
archive=out/'agendashops-hostinger.zip'
with ZipFile(archive,'w',ZIP_DEFLATED) as z:
    for folder in ['app','config','database','bin']:
        for p in sorted((ROOT/folder).rglob('*')):
            if p.is_file() and p.suffix not in ['.pyc']:
                z.write(p,'agendashops-private/'+p.relative_to(ROOT).as_posix())
    z.writestr('agendashops-private/.htaccess','Require all denied\n')
    z.writestr('agendashops-private/storage/.gitkeep','')
    z.write(ROOT/'deploy/.env.hostinger.example','agendashops-private/.env.example')
    for p in sorted((ROOT/'public').rglob('*')):
        if not p.is_file() or p.name=='router.php':continue
        rel=p.relative_to(ROOT/'public')
        if rel.parts[0]=='uploads' and rel.name!='.htaccess':continue
        z.write(p,'public_html/'+rel.as_posix())
    z.write(ROOT/'HOSTINGER.md','LEIA-ME-HOSTINGER.md')
    names=z.namelist()
    assert not any(n.endswith('/.env') or '/sessions/' in n for n in names)
print(archive)
