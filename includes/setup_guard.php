<?php

function requireCommandLineSetupApproval(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit('Not found.');
    }

    if (getenv('ALLOW_DB_SETUP') !== '1') {
        fwrite(STDERR, "Database setup is disabled. Set ALLOW_DB_SETUP=1 only after creating and verifying a backup.\n");
        exit(1);
    }
}
