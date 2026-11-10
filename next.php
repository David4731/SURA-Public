
<?php
require_once __DIR__ . '/../common.php';

printAgentAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    printJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    $q = $pdo->query(
        "SELECT *
         FROM print_jobs
         WHERE status = 'queued'
            OR (
                status = 'processing'
                AND lease_until < NOW()
            )
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE"
    );

    $job = $q->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->commit();
        printJson(['ok' => true, 'job' => null]);
    }

    $update = $pdo->prepare(
        "UPDATE print_jobs
         SET status = 'processing',
             attempts = attempts + 1,
             started_at = NOW(),
             lease_until = DATE_ADD(NOW(), INTERVAL 3 MINUTE),
             finished_at = NULL,
             error_message = NULL
         WHERE id = ?"
    );

    $update->execute([(int) $job['id']]);

    $payload = invoicePayload(
        $pdo,
        (int) $job['invoice_id']
    );

    $pdo->commit();

    printJson([
        'ok' => true,
        'job' => [
            'job_id' => $job['job_uuid'],
            'payload' => $payload
        ]
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[SURA PRINT NEXT] ' . $e->getMessage());

    printJson([
        'ok' => false,
        'error' => 'server_error'
    ], 500);
}
