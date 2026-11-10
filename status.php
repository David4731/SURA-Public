
<?php
require_once __DIR__ . '/common.php';

requireRole(['accountant', 'admin', 'checker', 'server']);

$uuid = trim($_GET['job_id'] ?? '');

if ($uuid === '') {
    printJson(['ok' => false, 'error' => 'job_id_required'], 400);
}

try {
    $q = getPDO()->prepare(
        'SELECT job_uuid, status, error_message, created_at,
                started_at, finished_at, attempts
         FROM print_jobs
         WHERE job_uuid = ?'
    );

    $q->execute([$uuid]);

    $job = $q->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        printJson(['ok' => false, 'error' => 'not_found'], 404);
    }

    printJson([
        'ok' => true,
        'job' => $job
    ]);

} catch (Throwable $e) {

    error_log('[SURA PRINT STATUS] ' . $e->getMessage());

    printJson([
        'ok' => false,
        'error' => 'database_error'
    ], 500);
}
