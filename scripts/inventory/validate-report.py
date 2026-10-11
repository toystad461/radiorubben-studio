import hashlib, json, pathlib, re, sys

def validate(report, collector):
    if report.get('productionWritten') is not False or report.get('existingSharedLock') is not True:
        raise ValueError('Reader or lock invariant failed')
    if report.get('collectorSha256') != hashlib.sha256(collector).hexdigest():
        raise ValueError('Installed collector differs from checked source')
    if not re.fullmatch(r'[a-f0-9]{40}', report.get('deployCommit', '')):
        raise ValueError('Missing production commit')
    allowed = {
        'studio-private/app/integrations/NewsDesk.php', 'studio-private/app/views/newsroom.php',
        'studio-private/app/news-input.php', 'studio-private/app/views/news-input.php',
        'studio-public/news-input.php', 'studio-public/assets/news-input.css',
        'studio-private/composer.json', 'studio-public/release.json',
        'studio-private/app/board.php', 'studio-private/app/users.php',
        'studio-private/app/source-identity.php', 'studio-private/app/bootstrap.php',
        'studio-private/app/views/head.php', 'studio-private/app/views/sidebar.php',
    }
    if set(report.get('files', {})) != allowed:
        raise ValueError('Unexpected inventory paths')
    for row in report['files'].values():
        if row.get('status') == 'absent' and row.get('absenceVerified') is True:
            continue
        if row.get('status') != 'present' or row.get('stableDuringRead') is not True:
            raise ValueError('Unstable file')
        if not re.fullmatch(r'[a-f0-9]{64}', row.get('sha256', '')):
            raise ValueError('Invalid file hash')
        if row.get('recordedLive') is not None and row['recordedLive'] != row['sha256']:
            raise ValueError('Production drift')

if __name__ == '__main__':
    validate(json.loads(pathlib.Path(sys.argv[1]).read_text()),
             pathlib.Path(__file__).with_name('read-production.php').read_bytes())
    print('Validated fixed read-only inventory')
