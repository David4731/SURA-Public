
<?php
require_once __DIR__ . '/../common.php';

printAgentAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    printJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    printJson(['ok' => false, 'error' => 'invalid_json'], 400);
}

$uuid = trim($data['job_id'] ?? '');
$success = !empty($data['success']);
$error = substr(trim($data['error'] ?? ''), 0, 500);

if ($uuid === '') {
    printJson(['ok' => false, 'error' => 'job_id_required'], 400);
}

$status = $success ? 'printed' : 'error';

try {
    $pdo = getPDO();

    $q = $pdo->prepare(
        "UPDATE print_jobs
         SET status = ?,
             error_message = ?,
             finished_at = NOW(),
             lease_until = NULL
         WHERE job_uuid = ?"
    );

    $q->execute([
        $status,
        $success ? null : ($error ?: 'Erreur impression'),
        $uuid
    ]);

    if ($q->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT id FROM print_jobs WHERE job_uuid = ?'
        );
        $check->execute([$uuid]);

        if (!$check->fetch()) {
            printJson(['ok' => false, 'error' => 'job_not_found'], 404);
        }
    }

    printJson(['ok' => true, 'status' => $status]);

} catch (Throwable $e) {
    error_log('[SURA PRINT COMPLETE] ' . $e->getMessage());

    printJson([
        'ok' => false,
        'error' => 'database_error'
    ], 500);
}
