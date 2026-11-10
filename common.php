<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
function printJson(array $data, int $status=200): void { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function printAgentAuth(): void { require_once __DIR__ . '/../../config/print_agent.php'; $h=$_SERVER['HTTP_AUTHORIZATION']??''; $token=preg_replace('/^Bearer\s+/i','',$h); if (!$token || !hash_equals(SURA_PRINT_AGENT_TOKEN,$token)) printJson(['ok'=>false,'error'=>'unauthorized'],401); }
function invoicePayload(PDO $pdo, int $id): array {
 $q=$pdo->prepare('SELECT i.*,o.table_no,o.client_name,o.client_phone,o.client_address,o.order_type,o.created_at AS order_time FROM invoices i JOIN orders o ON o.id=i.order_id WHERE i.id=?'); $q->execute([$id]); $i=$q->fetch(); if(!$i) throw new RuntimeException('Facture introuvable');
 $q=$pdo->prepare('SELECT item_name,quantity,unit_price,subtotal FROM order_items WHERE order_id=? ORDER BY id'); $q->execute([$i['order_id']]); $items=$q->fetchAll();
 return ['brand'=>'SURA','invoice_number'=>$i['invoice_number'],'created_at'=>$i['created_at'],'order_id'=>(int)$i['order_id'],'order_type'=>$i['order_type'],'table_no'=>$i['table_no'],'client_name'=>$i['client_name'],'client_phone'=>$i['client_phone'],'client_address'=>$i['client_address'],'total'=>(float)$i['total'],'payment_status'=>$i['payment_status'],'payment_method'=>$i['payment_method'],'payer_name'=>$i['payer_name'],'payer_phone'=>$i['payer_phone'],'items'=>$items,'footer'=>['SURA Restaurant - Olympia Tanjombato','MVola : 038 24 541 74','WhatsApp : +261 38 24 541 74','Merci de votre visite.']];
}
