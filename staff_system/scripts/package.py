"""Create and verify a portable ZIP without runtime sessions or test fixtures."""
from pathlib import Path
import hashlib
import json
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
target = Path(sys.argv[1]).resolve()
if target.is_relative_to(root):
    raise SystemExit('Place the archive outside pre-defense_system to avoid including itself.')
skip_prefixes = ('pre-defense_system/', 'new_system/', 'storage/logs/', 'storage/framework/', 'bootstrap/cache/', '.git/')
skip_files = {'.phpunit.result.cache'}
runtime_dirs = ['bootstrap/cache','storage/logs','storage/framework/cache/data',
                'storage/framework/sessions','storage/framework/views',
                'storage/app/private','storage/app/public','tests/Unit']
target.parent.mkdir(parents=True, exist_ok=True)
count = 0
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
    for file in sorted(root.rglob('*')):
        if not file.is_file():
            continue
        relative = file.relative_to(root).as_posix()
        if relative in skip_files or relative.startswith(skip_prefixes):
            continue
        archive.write(file, 'pre-defense_system/' + relative)
        count += 1
    for directory in runtime_dirs:
        archive.writestr('pre-defense_system/' + directory + '/', b'')
with zipfile.ZipFile(target) as archive:
    bad = archive.testzip()
    if bad:
        raise SystemExit('Corrupt archive member: ' + bad)
    required = ['artisan','bootstrap/app.php','vendor/autoload.php','database/database.sqlite',
                '.env','.env.example','README.md','start-system.bat','public/app.css',
                'public/app.js','public/design-assets/5a186.png','public/public-site.css',
                'database/pre_defense_system.sql','resources/views/public/page.blade.php','public/design-assets/public/provenance.json',
                'database/migrations/2026_10_02_230000_create_proposal_drafts.php',
                'database/migrations/2026_10_02_240000_add_organization_moderators.php']
    for name in required:
        if 'pre-defense_system/' + name not in archive.namelist():
            raise SystemExit('Missing archive member: ' + name)
digest = hashlib.sha256(target.read_bytes()).hexdigest()
target.with_suffix('.zip.sha256').write_text(digest + '  ' + target.name + '\n')
print(json.dumps({'archive': str(target), 'files': count, 'bytes': target.stat().st_size,
                  'sha256': digest, 'verification': 'all archive entries readable'}))
