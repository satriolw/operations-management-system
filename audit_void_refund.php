<?php
/**
 * Audit Void/Refund NEVIRA — populasi penuh (semua halaman, semua outlet).
 *
 * Cara pakai:
 *   NEVIRA_BASE_URL="https://api.nevira.id" \
 *   NEVIRA_TOKEN="<bearer token>" \
 *   START_DATE="2026-05-13" END_DATE="2026-06-12" \
 *   php audit_void_refund.php
 *
 * Opsional:
 *   HEAD_STORE_ROLE_IDS="3,7"   # id_role yang setara/di atas Kepala Toko
 *                               # (untuk membedakan self-approval sah vs pelanggaran)
 *
 * Catatan: skrip memakai field tingkat-transaksi (created_at, approve_refund_void_date)
 * yang berformat WIB — konsisten satu zona, aman untuk perbandingan tanggal.
 */

date_default_timezone_set('Asia/Jakarta');

$BASE  = rtrim(getenv('NEVIRA_BASE_URL') ?: 'https://api.nevira.id', '/');
$TOKEN = getenv('NEVIRA_TOKEN') ?: '';
$START = getenv('START_DATE') ?: date('Y-m-d', strtotime('-30 days'));
$END   = getenv('END_DATE')   ?: date('Y-m-d');
$HEAD_ROLES = array_filter(array_map('trim', explode(',', getenv('HEAD_STORE_ROLE_IDS') ?: '')));

if (!$TOKEN) {
    fwrite(STDERR, "ERROR: set NEVIRA_TOKEN.\n");
    exit(1);
}

/** Ambil semua halaman untuk satu status (VOID/REFUND). */
function fetchAll(string $base, string $token, string $status, string $start, string $end): array {
    $rows = [];
    $page = 1;
    do {
        $url = sprintf(
            '%s/api/transactions?status=%s&is_void_refund=true&start_date=%s&end_date=%s&per_page=50&page=%d',
            $base, $status, $start, $end, $page
        );
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", "Accept: application/json"],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 429) { sleep(2); continue; }        // hormati rate limit
        if ($code >= 400 || $body === false) {
            fwrite(STDERR, "HTTP {$code} pada {$status} page {$page}\n");
            break;
        }
        $json = json_decode($body, true);
        $data = $json['data'] ?? [];
        foreach ($data as $r) { $rows[] = $r; }
        $next = $json['next_page_url'] ?? null;
        $page++;
        usleep(200000); // 0.2s, sopan
    } while ($next);
    return $rows;
}

/** Klasifikasi alasan jadi taksonomi kasar. */
function reasonCategory(?string $note): string {
    $n = mb_strtolower($note ?? '');
    if ($n === '') return 'Tanpa alasan';
    foreach (['salah input','salah masuk','salah no','salah nota','salah outlet','kesalahan input','tergabung','terinput','input nota'] as $k)
        if (str_contains($n, $k)) return 'Input error';
    if (str_contains($n, 'belum')) return 'Belum bayar (abandoned)';
    foreach (['ingin','ganti','ubah','pisah','customer','custemer'] as $k)
        if (str_contains($n, $k)) return 'Permintaan/ubah customer';
    return 'Lain';
}

echo "Menarik data VOID & REFUND ({$START} s/d {$END})...\n";
$void   = fetchAll($BASE, $TOKEN, 'VOID', $START, $END);
$refund = fetchAll($BASE, $TOKEN, 'REFUND', $START, $END);
$all = array_merge(
    array_map(fn($r) => $r + ['_type' => 'VOID'], $void),
    array_map(fn($r) => $r + ['_type' => 'REFUND'], $refund),
);
$n = count($all);
if ($n === 0) { echo "Tidak ada data.\n"; exit; }

// ---- akumulator ----
$paidByType = ['VOID' => [], 'REFUND' => []];
$selfApproval = []; $selfApprovalViolation = [];
$reqNotCashier = 0;
$reason = []; $byCashier = []; $byRequester = []; $byOutlet = [];
$lags = []; $crossDay = []; $orphaned = [];
$nominal = ['VOID' => 0, 'REFUND' => 0];
$batch = []; // key approver|menit -> ids

foreach ($all as $r) {
    $type = $r['_type'];
    $paidByType[$type][$r['payment_status'] ?? 'UNKNOWN'] = true;
    $nominal[$type] += (int)($r['grand_total'] ?? 0);

    $by = $r['refund_void_by'] ?? null;
    $appr = $r['refund_void_approved_by'] ?? null;
    $cashier = $r['id_cashier'] ?? null;
    if ($by !== null && $by === $appr) {
        $selfApproval[] = $r;
        // pelanggaran jika role penyetuju TIDAK termasuk Kepala Toko ke atas (bila peta role disediakan)
        $approverRole = $r['cashier']['id_role'] ?? null; // proxy; idealnya lookup user penyetuju
        if (!empty($GLOBALS['HEAD_ROLES']) && $approverRole !== null
            && !in_array((string)$approverRole, $GLOBALS['HEAD_ROLES'], true)) {
            $selfApprovalViolation[] = $r;
        }
    }
    if ($by !== null && $cashier !== null && (string)$by !== (string)$cashier) $reqNotCashier++;

    $note = $r['void_notes'] ?? $r['refund_notes'] ?? null;
    $reason[reasonCategory($note)] = ($reason[reasonCategory($note)] ?? 0) + 1;

    if ($cashier !== null) $byCashier[$cashier] = ($byCashier[$cashier] ?? 0) + 1;
    if ($by !== null)      $byRequester[$by]    = ($byRequester[$by] ?? 0) + 1;
    $outlet = $r['outlet_name'] ?? $r['id_outlet'] ?? '?';
    $byOutlet[$outlet] = ($byOutlet[$outlet] ?? 0) + 1;

    $req = $r['request_refund_void_date'] ?? null;
    $ap  = $r['approve_refund_void_date'] ?? null;
    if ($req && $ap) {
        $lagH = (strtotime($ap) - strtotime($req)) / 3600;
        $lags[] = ['id' => $r['id_transaction'] ?? null, 'type' => $type, 'lag' => $lagH];
        $key = ($appr ?? '?') . '|' . substr($ap, 0, 16);
        $batch[$key][] = $r['id_transaction'] ?? null;
    }
    $created = $r['created_at'] ?? null;
    if ($created && $ap && substr($created, 0, 10) !== substr($ap, 0, 10)) {
        $crossDay[] = ['id' => $r['id_transaction'] ?? null, 'type' => $type,
            'nota' => substr($created, 0, 10), 'appr' => substr($ap, 0, 10),
            'paid' => $r['payment_status'] ?? '', 'amount' => (int)($r['grand_total'] ?? 0)];
    }
    if ((int)($r['progress_percentage'] ?? 0) > 0) {
        $orphaned[] = ['id' => $r['id_transaction'] ?? null, 'type' => $type,
            'progress' => $r['progress_percentage'], 'amount' => (int)($r['grand_total'] ?? 0),
            'note' => $note];
    }
}

// ---- output ----
function rp(int $v): string { return 'Rp' . number_format($v, 0, ',', '.'); }
function median(array $a): float { sort($a); $c = count($a); if (!$c) return 0; $m = intdiv($c, 2); return $c % 2 ? $a[$m] : ($a[$m-1] + $a[$m]) / 2; }

echo "\n================ HASIL AUDIT ================\n";
echo "Total: {$n} (VOID " . count($void) . ", REFUND " . count($refund) . ")\n";

echo "\n[Status bayar per tipe]\n";
foreach ($paidByType as $t => $set) echo "  {$t}: " . implode(', ', array_keys($set)) . "\n";

$sa = count($selfApproval);
echo "\n[Self-approval] {$sa}/{$n} (" . round(100*$sa/$n) . "%)\n";
if (!empty($HEAD_ROLES)) echo "  -> dugaan pelanggaran (role < Kepala Toko): " . count($selfApprovalViolation) . "\n";
else echo "  (set HEAD_STORE_ROLE_IDS untuk memisahkan sah vs pelanggaran)\n";

echo "\n[Requester != cashier]: {$reqNotCashier}/{$n}\n";

echo "\n[Taksonomi alasan]\n";
arsort($reason);
foreach ($reason as $k => $v) printf("  %-28s %d (%d%%)\n", $k, $v, round(100*$v/$n));

echo "\n[Approval lag request->approve]\n";
$lagVals = array_column($lags, 'lag');
echo "  median: " . round(median($lagVals), 2) . " jam ; max: " . round(max($lagVals ?: [0]), 1) . " jam\n";

echo "\n[Batch approval (approver+menit sama, >1)]\n";
$batchFound = false;
foreach ($batch as $key => $ids) {
    if (count($ids) > 1) { $batchFound = true; [$a,$m] = explode('|', $key); echo "  approver {$a} @ {$m} -> " . count($ids) . " item: " . implode(',', $ids) . "\n"; }
}
if (!$batchFound) echo "  (tidak ada)\n";

echo "\n[Cross-day: tgl nota != tgl approve] " . count($crossDay) . "/{$n}\n";
$cdSum = array_sum(array_column($crossDay, 'amount'));
echo "  total nominal cross-day: " . rp($cdSum) . "\n";

echo "\n[Kandidat orphaned production (progress>0)] " . count($orphaned) . "\n";
foreach (array_slice($orphaned, 0, 10) as $o)
    echo "  id {$o['id']} {$o['type']} progress {$o['progress']}% " . rp($o['amount']) . " — {$o['note']}\n";

echo "\n[Nominal]\n";
echo "  VOID   total: " . rp($nominal['VOID']) . "\n";
echo "  REFUND total: " . rp($nominal['REFUND']) . "\n";

echo "\n[Top 5 requester]\n";
arsort($byRequester);
foreach (array_slice($byRequester, 0, 5, true) as $u => $c) echo "  user {$u}: {$c}\n";
echo "[Per outlet]\n";
arsort($byOutlet);
foreach ($byOutlet as $o => $c) echo "  {$o}: {$c}\n";

echo "\nSelesai. Catatan: self-approval sah/pelanggaran butuh peta id_role (HEAD_STORE_ROLE_IDS).\n";
echo "id_role penyetuju di sini memakai proxy dari objek cashier — idealnya lookup user penyetuju sebenarnya.\n";
