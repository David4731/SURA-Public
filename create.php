
<?php

require_once __DIR__ . '/common.php';

requireRole(['accountant', 'admin', 'checker', 'server']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    printJson([
        'ok' => false,
        'error' => 'method_not_allowed'
    ], 405);
}

try {

    // Lire les données envoyées par le navigateur
    $body = file_get_contents('php://input');
    $raw = json_decode($body, true);

    if (!is_array($raw)) {
        $raw = $_POST;
    }

    $invoiceId = (int) ($raw['invoice_id'] ?? 0);

    if ($invoiceId < 1) {
        printJson([
            'ok' => false,
            'error' => 'invoice_id_required'
        ], 400);
    }

    // Vérifier l'utilisateur connecté
    $user = currentUser();

    if (!$user || empty($user['id'])) {
        printJson([
            'ok' => false,
            'error' => 'user_not_authenticated'
        ], 401);
    }

    // Connexion à la base de données
    $pdo = getPDO();

    // Vérifier la facture
    $check = $pdo->prepare(
        'SELECT id FROM invoices WHERE id = ? LIMIT 1'
    );

    $check->execute([$invoiceId]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        printJson([
            'ok' => false,
            'error' => 'invoice_not_found'
        ], 404);
    }

    // Générer un identifiant unique
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(0, 4095) | 0x4000,
        random_int(0, 16383) | 0x8000,
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(0, 65535)
    );

    // Ajouter le travail à la file d'impression
    $insert = $pdo->prepare(
        "INSERT INTO print_jobs
            (job_uuid, invoice_id, requested_by, status)
         VALUES
            (?, ?, ?, 'queued')"
    );

    $insert->execute([
        $uuid,
        $invoiceId,
        (int) $user['id']
    ]);

    // Réponse JSON
    printJson([
        'ok' => true,
        'job_id' => $uuid,
        'status' => 'queued',
        'message' => 'Ticket ajouté à la file d’impression.'
    ]);

} catch (Throwable $e) {

    // Enregistrer l'erreur dans le journal PHP
    error_log(
        '[SURA PRINT CREATE] ' .
        $e->getMessage() .
        ' dans ' .
        $e->getFile() .
        ':' .
        $e->getLine()
    );

    // Réponse temporaire de diagnostic
    printJson([
        'ok' => false,
        'error' => 'print_job_creation_failed',
        'debug' => $e->getMessage()
    ], 500);
}
