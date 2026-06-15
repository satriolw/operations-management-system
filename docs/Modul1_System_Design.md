# Modul 1 — Report & Signal Engine: System Design

**Aplikasi:** Operations Management System (OMS) · Apique Group. Modul 1 (laporan harian otomatis + sinyal operasional + lapisan Admin).
**Tanggal:** 12 Juni 2026 (rev 16 Juni) · **Versi:** 1.2 (Admin/CRUD, Template drag & drop, Identity/Auth, audit trail, edge-case hardening, token re-auth NEVIRA)
**Sumber:** Blueprint PRD v1.0 + audit data void/refund.

## Konteks Teknis (dikonfirmasi)

| Aspek | Keputusan / Asumsi |
|---|---|
| Stack | **PHP / Laravel** (tim menguasai; selaras dengan NEVIRA yang juga Laravel — terlihat dari pola paginasi response) |
| Hosting | **Infra internal existing**, satu ekosistem dengan NEVIRA/ERP |
| Integrasi NEVIRA | **Belum diketahui** apakah ada webhook → **asumsi: REST polling saja**, desain dibuat *event-ready* agar mudah pindah ke webhook |
| Skala | **15–50 outlet** (beban kecil; hindari over-engineering) |

> Asumsi yang perlu dikonfirmasi ditandai dengan **[ASUMSI]** di sepanjang dokumen.

---

## 1. Requirements

### 1.1 Functional

- **F1.** Tiap outlet: tarik dashboard harian NEVIRA, render laporan profesional + gambar dashboard, kirim ke tujuan WhatsApp pada jam terjadwal.
- **F2.** Pisahkan revenue Terealisasi (paid) vs Piutang (unpaid).
- **F3.** Penyesuaian Revenue: deteksi VOID+REFUND yang disetujui hari ini atas nota hari lampau (lookback 7 hari), sisipkan blok koreksi.
- **F4.** Deteksi outlet diam terhadap baseline per outlet; alert < 60 menit.
- **F5.** Sinyal integritas: self-approval, batch-approval, orphaned-production, KPI akurasi input, aging piutang.
- **F6.** Simpan output (status laporan, sinyal, koreksi) dalam skema yang dapat di-query LBE.
- **F7.** Mode pengiriman dapat ditukar: hybrid (draft ke Head Store) ↔ Opsi A (Groups API).

### 1.2 Non-Functional

| Atribut | Target | Catatan |
|---|---|---|
| Skala | 15–50 outlet, ~30–100 user | Beban harian sangat kecil (lihat §4.1) |
| Latency | Bukan real-time kritis | Laporan harian per jadwal; alert outlet diam < 60 mnt |
| Availability | "Business hours" cukup; tidak 24/7 mission-critical | Kegagalan harus terdeteksi, bukan silent |
| Konsistensi | NEVIRA = sumber kebenaran tunggal | App hanya menyimpan output turunan |
| Keamanan | Token NEVIRA & kredensial WA di secret store; minimalkan PII customer | Lihat §3.6 |
| Auditability | Semua kiriman & sinyal berstempel waktu + dapat di-replay | |
| Biaya | Rendah; reuse infra internal | Tanpa komponen distribusi mahal |

### 1.3 Constraints

- Laravel monorepo/ekosistem internal; reuse DB engine yang sama (MySQL/MariaDB) **[ASUMSI: NEVIRA pakai MySQL]**.
- Tidak boleh menduplikasi kebenaran transaksi NEVIRA.
- Opsi A (Groups API) terblokir oleh proses OBA (durasi tak diketahui) → MVP harus jalan via hybrid.

---

## 2. High-Level Design

### 2.1 Gaya arsitektur: Modular Monolith (Laravel)

Untuk skala ini, **satu aplikasi Laravel** dengan pemisahan domain via modul/namespace adalah pilihan paling sederhana dan paling cepat dirilis. Bukan microservices (tidak ada kebutuhan skala/independensi rilis yang membenarkan biayanya). Modul:

- `Ingestion` — klien & poller NEVIRA.
- `Reporting` — agregasi, render pesan & gambar, compose.
- `Revenue` — penyesuaian revenue & piutang.
- `Signals` — outlet diam + anomali integritas.
- `Delivery` — abstraksi WhatsApp (hybrid / cloud-api).
- `Templating` — template laporan (master→override, token, drag & drop builder).
- `Identity` — auth + role/permission OMS, hook SSO ke LBE/ERP.
- `Admin` — CRUD master data (outlet, akun/target WhatsApp, template, user), preview, resend.

### 2.2 Diagram komponen

```
                         ┌───────────────────────────────────────────────┐
                         │            Operations System (Laravel)         │
                         │                                                │
  ┌─────────────┐  poll  │  ┌────────────┐   dispatch   ┌─────────────┐   │
  │   NEVIRA    │◀───────┼──│ Ingestion  │─────────────▶│   Queue     │   │
  │  REST API   │──────▶ │  │ (HTTP client│              │ (Redis/DB)  │   │
  │ (Laravel)   │  JSON  │  │  + poller)  │              └─────┬───────┘   │
  └─────────────┘        │  └────────────┘                    │ workers   │
        ▲                │                                     ▼           │
        │ (future        │  ┌──────────┐ ┌──────────┐ ┌──────────────┐     │
        │  webhook)      │  │Reporting │ │ Revenue  │ │   Signals    │     │
        └────────────────┼  └────┬─────┘ └────┬─────┘ └──────┬───────┘     │
                         │       │            │              │             │
                         │       └──────┬─────┴──────┬───────┘             │
                         │              ▼            ▼                      │
                         │        ┌──────────┐  ┌──────────────┐           │
                         │        │ Delivery │  │  MySQL        │           │
                         │        │ (WA)     │  │  (output DB)  │◀────┐     │
                         │        └────┬─────┘  └──────────────┘     │     │
                         │             │                              │ read│
                         └─────────────┼──────────────────────────────┼────┘
                                       ▼                              │
                          ┌──────────────────────┐          ┌─────────┴───────┐
                          │ WhatsApp:             │          │   LBE Dashboard │
                          │  hybrid → Head Store  │          │  (fase berikut) │
                          │  Opsi A → Groups API  │          └─────────────────┘
                          └──────────────────────┘
        ┌───────────────────────┐
        │ Laravel Scheduler(cron)│──► dispatch job harian/jam-an per outlet
        └───────────────────────┘
```

### 2.3 Data flow (laporan harian)

```
cron(jam laporan outlet X)
  └─▶ GenerateDailyReportJob(outlet, date)
        ├─ Ingestion: GET /reports/dashboard           → metrik harian
        ├─ Revenue:   GET /transactions?payment_status=UNPAID → piutang  (F2)
        ├─ Revenue:   RevenueAdjustment(lookback 7d)   → blok koreksi    (F3)
        ├─ Reporting: render teks (hide-zero) + PNG dashboard
        ├─ Reporting: compose payload → simpan report_run
        └─ Delivery:  kirim (hybrid/cloud) → simpan report_delivery
```

### 2.4 Data flow (sinyal)

```
cron(titik cek 11/14/17) ─▶ SilentOutletCheckJob(outlet)  → bandingkan vs baseline → signal_event + alert
cron(harian)            ─▶ AnomalyScanJob(outlet)         → void/refund 1 hari → self/batch/orphaned/input-KPI → signal_event
cron(harian)            ─▶ AgingPiutangJob(outlet)        → unpaid > X hari → signal_event
```

### 2.5 Pilihan storage

| Kebutuhan | Pilihan | Alasan |
|---|---|---|
| Output DB | **MySQL/MariaDB** | Selaras dengan Laravel & NEVIRA; relasional cocok untuk laporan & audit |
| Queue | **Redis** (driver `redis`) bila tersedia, fallback **database queue** | Async job; Redis lebih efisien tapi DB queue cukup untuk skala ini |
| Cache (data NEVIRA per-run) | **Redis** TTL pendek, atau cache file | Hindari fetch berulang dalam satu run; bukan penyimpanan permanen |
| Secrets | Laravel config + secret manager internal / `.env` terenkripsi | Token NEVIRA & kredensial WA |

---

## 3. Deep Dive

### 3.1 Batas integrasi NEVIRA — API vs Direct DB (keputusan penting)

Karena infra internal sama, ada godaan membaca **langsung dari DB NEVIRA**. Trade-off:

| | REST API (dipilih) | Direct DB read |
|---|---|---|
| Kopling | Longgar (kontrak API) | Ketat ke skema internal NEVIRA |
| Konsistensi prinsip | Sesuai "NEVIRA sumber tunggal via API" | Melanggar batas; rapuh saat NEVIRA migrasi skema |
| Performa agregasi berat | Lebih lambat (paginasi) | Lebih cepat |
| Risiko | Rendah | Tinggi (perubahan skema diam-diam merusak app) |

**Keputusan:** **API-first.** Bila nanti agregasi terbukti berat (tidak diharapkan pada 15–50 outlet), pertimbangkan **read-replica** NEVIRA khusus baca sebagai optimisasi terisolasi — bukan default. Desain Ingestion menyembunyikan sumber di balik interface sehingga sumber bisa ditukar tanpa menyentuh domain lain.

### 3.2 Ingestion: poller event-ready

```php
interface TransactionSource {
    public function dailyDashboard(int $outletId, CarbonInterface $date): DashboardDTO;
    public function voidRefunds(int $outletId, DateRange $range): Collection;   // is_void_refund=true
    public function unpaid(int $outletId, DateRange $range): Collection;        // payment_status=UNPAID
}
```

- Implementasi awal: `NeviraApiSource` (polling, Laravel `Http` + retry).
- **Event-ready:** jika NEVIRA kelak menyediakan webhook, tambahkan `WebhookReceiverController` yang menulis event ke queue yang sama — domain tidak berubah. **[ASUMSI: webhook belum ada]**
- Paginasi: ikuti `last_page`/`next_page_url`; kumpulkan semua halaman dengan batas aman.
- **Token lifecycle (login 24 jam, dikonfirmasi):** `NeviraApiSource` punya token manager — login endpoint dengan service credential menghasilkan token berumur 24 jam, di-cache bersama (Redis) + waktu perolehan. **Refresh proaktif** menjelang 24 jam; **reaktif** pada 401 dengan **single-flight re-login** lalu retry sekali; gagal → alert + fallback (tidak loop). 401/403 dipisah dari error transient (429/5xx). Lihat OPS-108. Service credential disimpan di secret store (bukan hanya token).

### 3.3 Data model (output DB)

Hanya menyimpan **turunan**, bukan salinan transaksi. Referensi ke NEVIRA via `transaction_number`/`id_transaction`.

```
outlets                      (config: id_outlet, name, report_time, tz, deliver_mode, wa_target, active)
outlet_operating_hours       (id_outlet, weekday, open, close, is_holiday_date)
outlet_baselines             (id_outlet, checkpoint_hour, avg_txn, sample_days, updated_at)

report_runs                  (id, id_outlet, report_date, status, payload_text, image_path,
                              total_sales, realized, receivable, txn_count, created_at)
report_deliveries            (id, report_run_id, channel, target, status, error, sent_at, idempotency_key)

revenue_adjustments          (id, id_outlet, report_run_id, transaction_number, type[VOID|REFUND],
                              amount, reason, nota_date, approved_at, restated_for_date, created_at)

signal_events                (id, id_outlet, type[SILENT_OUTLET|SELF_APPROVAL|BATCH_APPROVAL|
                              ORPHANED_PRODUCTION|INPUT_ERROR_KPI|AGING_PIUTANG],
                              severity, ref_transaction_number, id_cashier, payload_json,
                              status[OPEN|REVIEWED|DISMISSED], detected_at)

cashier_input_scores         (id_outlet, id_cashier, period, error_count, txn_count, rate, updated_at)
```

Indeks penting: `report_runs(id_outlet, report_date)`, `signal_events(id_outlet, type, detected_at)`, `revenue_adjustments(restated_for_date)`. Semua tabel ber-`id_outlet` agar mudah di-query LBE per outlet.

**Tabel tambahan (v1.1 — Admin, Template, Identity, Review):**

```
whatsapp_accounts     (id, label, phone_number, provider, oba_status, credentials_ref, active)
delivery_targets      (id, id_outlet, investor_label, channel_type, group_id, group_ready,
                      wa_account_id, deliver_mode[hybrid|assisted|full_auto], template_id, active)
report_templates      (id, scope[master|outlet|target], parent_template_id, name,
                      layout_json, meta_template_ref, active, updated_by, updated_at)
outlet_checkpoints    (id, id_outlet, checkpoint_hour, threshold_pct)   # jam cek diam dinamis
outlet_operating_hours(id, id_outlet, weekday, open, close, is_holiday_date)

users                 (id, name, email, password_hash, status, external_idp_ref nullable)
roles, permissions, role_user, permission_role   # mis. via spatie/laravel-permission
review_logs           (id, subject_type[signal|revenue_adjustment], subject_id,
                      reviewer_user_id, outcome, note, evidence_path nullable, reviewed_at)
```

`delivery_targets` menggantikan kolom `deliver_mode` pada `outlets` (satu outlet bisa >1 investor). `outlet_checkpoints` memindahkan jam cek outlet-diam dari hardcode ke data.

### 3.4 Kontrak API

**Yang dikonsumsi (NEVIRA):** lihat PRD §6.2 (`/reports/dashboard`, `/transactions?status=VOID|REFUND&is_void_refund=true`, `?payment_status=UNPAID`).

**Yang diekspos (aplikasi sendiri, internal):**

| Method | Path | Guna |
|---|---|---|
| GET | `/admin/outlets` · POST/PUT | CRUD konfigurasi outlet & jadwal |
| GET | `/reports/{outlet}/{date}/preview` | Pratinjau laporan sebelum kirim (dry-run) |
| POST | `/reports/{outlet}/{date}/resend` | Kirim ulang manual (idempoten) |
| GET | `/signals?outlet=&type=&status=` | Daftar sinyal untuk ops |
| POST | `/signals/{id}/review` | Tandai sinyal "reviewed/dismissed" |
| GET | `/lbe/outputs?...` | Endpoint read-only untuk LBE (atau view DB) |
| POST | `/webhooks/nevira` | *(disiapkan)* penerima event bila webhook tersedia |

### 3.5 Queue, scheduling, idempotency

- **Scheduler** (`app/Console/Kernel.php`): loop outlet aktif → dispatch job pada `report_time` masing-masing (timezone Asia/Jakarta); titik cek diam pada jam tetap.
- **Queue jobs** (async): `GenerateDailyReportJob`, `RevenueAdjustmentJob`, `SilentOutletCheckJob`, `AnomalyScanJob`, `AgingPiutangJob`, `DeliverReportJob`.
- **Idempotency:** kunci `report:{outlet}:{date}` dan `delivery:{report_run}:{channel}` mencegah kiriman ganda saat retry/replay.
- **Concurrency:** worker tunggal sudah cukup; gunakan `WithoutOverlapping` middleware per outlet.

### 3.6 Error handling, retry, keamanan

- **Retry:** Laravel job `tries=3`, `backoff=[60,300,900]`. Kegagalan final → `failed_jobs` + `signal/alert ops`.
- **Watchdog (anti silent-failure):** job harian memverifikasi setiap outlet aktif punya `report_delivery` sukses pada jendela jadwalnya; jika tidak → alert (memenuhi NFR "kegagalan terdeteksi").
- **Keamanan & PII:** record void/refund mengandung data customer (nama, telепon). **Simpan hanya yang diperlukan untuk sinyal** (transaction_number, nominal, alasan, id_cashier, tanggal) — **jangan persist PII customer** di output DB. Token NEVIRA & kredensial WA di secret store, tidak di repo. Akses LBE read-only.

### 3.7 Penyesuaian Revenue — logika inti

```
window = [today-7d, today]
rows = NEVIRA.voidRefunds(outlet, window)           # VOID + REFUND, is_void_refund=true
corrections = rows.filter(r =>
        date(r.approve_refund_void_date) == today    # disetujui hari ini
     && date(r.created_at) < today)                   # nota hari lampau
group by date(r.created_at) → restate revenue per tanggal nota
persist revenue_adjustments; render blok bila corrections non-empty
```

Mencakup VOID (unpaid) & REFUND (paid) karena `total_sales` NEVIRA memasukkan piutang (lihat PRD §8.4).

### 3.8 Cutover Delivery: Hybrid → Assisted → Opsi A

Perpindahan dari hybrid ke Groups API dirancang sebagai **pergantian konfigurasi per target (per investor/grup), bukan rombak kode**. Lapisan Delivery menyembunyikan transport; domain Reporting/Revenue/Signals tidak tersentuh.

**Tiga mode** (satu interface `Deliverer`, dikontrol konfigurasi per target):

| Mode | Alur | Gerbang manusia | Implementasi |
|---|---|---|---|
| `hybrid` | App generate → kirim draft ke Head Store → Head Store paste manual ke grup | Ya (paste) | `HybridDeliverer` |
| `assisted` | App generate → Head Store klik "Setujui & Kirim" → app kirim ke grup via API | Ya (satu klik) | `CloudApiDeliverer` + flag `requires_approval` |
| `full_auto` | App kirim langsung ke grup tanpa manusia | Tidak | `CloudApiDeliverer` |

**Progresi yang disarankan:** `hybrid → assisted → full_auto`. Hybrid/assisted berfungsi sebagai **periode validasi konten** sebelum manusia dicabut dari alur — penting karena blok Terealisasi/Piutang & Penyesuaian Revenue sensitif.

**`deliver_mode` disimpan per target** (kolom pada `outlets`, atau tabel `delivery_targets` bila satu outlet punya >1 investor).

**Precondition cutover ke assisted/full_auto (gerbang kesiapan):**
- `group_id` valid (dibuat via Groups API), DAN
- peserta grup sudah mengandung investor (join via invite link), DAN
- approved Groups template tersedia, DAN
- kredensial Cloud API aktif (OBA terverifikasi).

**Strategi rollout:**
- **Canary:** pindahkan 1 investor lebih dulu, pantau 2–3 hari, baru sisanya.
- **Fallback otomatis:** bila pengiriman Groups API gagal (template ditolak / isu grup), `Deliverer` jatuh balik ke `hybrid` untuk kiriman itu → tidak ada kegagalan diam-diam.
- **Idempotency:** tepat satu channel aktif per target per hari; ganti mode tidak menyebabkan kiriman ganda.

**Konsekuensi desain konten:** renderer laporan harus menghasilkan konten yang **muat ke approved Groups template**, agar isi hybrid & Opsi A identik. Approval template diurus paralel dengan OBA.

```
[hybrid] --(grup siap + template + OBA)--> [assisted] --(konten terbukti akurat N minggu)--> [full_auto]
   ^                                                                                              |
   └──────────────── fallback otomatis bila pengiriman Groups API gagal ──────────────────────────┘
```

### 3.9 Template Engine: dinamis, master → override, drag & drop

**Model konten terpisah dari transport.** Template mendefinisikan *apa* yang tampil & wording; transport menentukan *bagaimana* dikirim.

- **Pewarisan:** `report_templates` scope `master` (grup) → `outlet`/`target` (override). Outlet mewarisi master, boleh meng-custom karena background investor beda.
- **Token:** blok terikat field response NEVIRA — `{{total_sales}}`, `{{realized}}`, `{{piutang}}`, `{{txn_count}}`, `{{avg_transaction}}`, `{{penyesuaian_revenue}}`, dll. Disimpan sebagai `layout_json` (urutan blok + token + teks statis).
- **Builder drag & drop:** UI menyusun/mengurutkan blok, edit wording, **preview real-time dari sample response** outlet. Output = master atau override.

**⚠️ Batas transport (keputusan kunci).** Pesan bisnis-initiated ke grup WA WAJIB approved Meta template (variabel `{{1}}…`). Karena itu:

| Mode | Render | Catatan |
|---|---|---|
| `hybrid` | `layout_json` → teks bebas | Head Store paste; kebebasan penuh |
| `assisted` / `full_auto` | bagian variabel → **approved Meta template fleksibel** (body 1 parameter besar) | konten harus muat; bila tidak → guard tolak + fallback hybrid |

Pipeline laporan **tidak diblokir builder**: ia memakai template aktif/override apa pun; bila belum ada, pakai **seed default**. Builder berjalan paralel.

### 3.10 Identity & Auth (sendiri, siap SSO ke LBE)

- OMS punya **auth + role/permission sendiri** (mis. `spatie/laravel-permission`). Role app: `admin`, `head_store`, `area_manager`, `ops`. Gate aksi sensitif (Setujui & Kirim, review sinyal, edit master data).
- **Hook federasi:** interface `IdentityProvider` (lokal sekarang) agar bisa **connect/SSO ke LBE/ERP** nanti tanpa rombak. Saat SSO aktif, referensikan identitas pusat — jangan duplikasi master karyawan.
- **Dua identitas berbeda, jangan dicampur:** (i) **user OMS** (login app) vs (ii) **aktor NEVIRA** (`id_cashier`/`id_role` pada transaksi, dipakai sinyal). Peta `id_role → level` (OPS-601) dari NEVIRA, bukan user OMS.
- **Bukan digabung ke LBE:** LBE = dashboard analitik (beda concern). Keputusan "di mana identitas pusat ERP tinggal" diputuskan di level ERP.

### 3.11 Audit Trail Tinjauan (evidence)

Setiap tindak lanjut atas `signal_events` & `revenue_adjustments` menulis ke `review_logs`: `reviewer_user_id`, `reviewed_at`, `outcome`, `note` (wajib), `evidence_path` (opsional). Append-only / ter-audit — "sudah ditinjau" harus dapat dibuktikan. Bukan workflow approval berlapis; cukup jejak akuntabilitas. Relevan karena tema kontrol (self-approval).

### 3.12 Admin & Master Data CRUD

Semua master data dikelola via Admin UI, **tanpa nilai hardcode**: outlet (jam laporan, `outlet_checkpoints` jam cek diam dinamis, ambang, jam operasional/libur), `whatsapp_accounts`, `delivery_targets` (mode per target, dapat diubah sewaktu-waktu; perpindahan ke Opsi A tetap lewat gerbang kesiapan), `report_templates`, users/roles, dan referensi (`id_role → level`, kalender libur). Pertimbangkan Filament/Livewire untuk CRUD cepat.

> **Kredensial NEVIRA** TIDAK jadi fitur CRUD — disimpan di `.env`/secret store (satu kredensial stabil). Akun WhatsApp (OPS-804) menyimpan **referensi** ke secret store (`credentials_ref`), bukan secret mentah. Rotasi non-dev (bila perlu) = aksi write-only terenkripsi, bukan CRUD.

### 3.13 Edge Cases & Hardening (v1.2)

Hasil challenge edge-case + keputusan user (16 Juni 2026).

**Asumsi terkonfirmasi (jadikan invariant + contract test):**
- Refund **selalu penuh** → `restate amount = grand_total` valid. Pecahkan eksplisit bila muncul refund parsial.
- Transaksi **tidak bisa diedit langsung** (hanya via void/refund) → Penyesuaian Revenue **lengkap**, tak ada blind-spot.
- **Investor 1:1 outlet**, dan **investor tidak login app** (terima laporan via WhatsApp saja) → otorisasi per-outlet hanya untuk **staf internal**.

**Hardening yang ditambahkan:**

| # | Case | Solusi |
|---|---|---|
| 1 | Nomor WA hilang/banned → grup yatim | DR: state akun `active/lost/recovering`; runbook re-provision → OBA → recreate grup → re-invite; fallback otomatis hybrid. **OPS-307** |
| 2 | Hybrid: kiriman ke investor tak terverifikasi | Head Store tap **"Sudah saya kirim"**; watchdog pakai status ini, bukan "draft terkirim ke Head Store". **OPS-302** |
| 3 | Outlet buka tapi nol transaksi / outlet tutup | Buka-nol → **tetap kirim** dengan catatan jujur + alert internal; tutup/libur → suppress atau beri catatan. **OPS-1001** |
| 4 | Baseline outlet-diam bias hari libur/nol | Hitung baseline **hanya dari hari buka & bertransaksi**. **OPS-501** |
| 5 | Alert fatigue (75% = salah input) | **Severity tiering**: high (self-approval pelanggaran, void besar, orphaned) real-time; low (input error rutin) **digest**. **OPS-1002** |
| 6 | Konflik kepentingan tinjauan | **Reviewer ≠ subjek**; eskalasi bila Head Store terlibat. **OPS-606** |
| 7 | Akses data lintas-outlet | **Scoping per-outlet** untuk staf internal (assignment user↔outlet). Investor di luar app. **OPS-1003** |
| 8 | Biaya WhatsApp membengkak (loop bug) | Counter pesan + cap/alert budget + **circuit breaker**. **OPS-706** |
| 9 | Edit master template merusak semua outlet | **Versioning**: draft → preview → publish, rollback. **OPS-1004** |
| 10 | KWL self-service beda pola | Modul 1 = **LW dulu**; KWL = profil/adapter terpisah (sinyal input-error/self-approval mungkin tak relevan tanpa kasir). Non-goal v1. |
| 11 | Transaksi masuk setelah jam kirim | Periode = **hari kalender penuh, kirim setelah hari ditutup** (atau cutoff eksplisit & dikomunikasikan). **OPS-1005** |
| 12 | Investor hanya label string | **Investor master ringan** (nama, kontak WA, outlet, sejak kapan) untuk re-invite & link CRM. **OPS-1005** |

---

## 4. Scale & Reliability

### 4.1 Estimasi beban (50 outlet, batas atas)

| Pekerjaan | Frekuensi | Volume/hari | Catatan |
|---|---|---|---|
| Laporan harian | 1×/outlet | 50 | + 50 fetch dashboard, 50 fetch unpaid |
| Penyesuaian revenue | 1×/outlet | 50 | fetch void/refund 7 hari (paginated) |
| Cek outlet diam | 3×/outlet | 150 | fetch ringan (hitung transaksi) |
| Anomali + aging | 1×/outlet | 100 | reuse data void/refund |
| **Total panggilan NEVIRA** | | **~ ratusan/hari** | beban sangat rendah |

Kesimpulan: **satu app server + satu worker + scheduler** lebih dari cukup. Tidak perlu sharding, autoscaling, atau message broker eksternal.

### 4.2 Scaling & failover

- **Vertikal dulu**, horizontal hanya bila outlet tumbuh ekstrem (>150) — tambah worker, Redis queue.
- **Failover:** job idempoten + retry + replay (`--date=` backfill) menutup gangguan sementara NEVIRA/WA.
- **Rate limit NEVIRA [ASUMSI tak diketahui]:** poller hormati 429 dengan backoff; jadwalkan job menyebar (jangan semua outlet di detik yang sama).

### 4.3 Monitoring & alerting

- **Metrik:** jumlah laporan terkirim/gagal, latensi job, kegagalan kirim WA (<2% target), jumlah sinyal per tipe.
- **Log terstruktur** per job (outlet, date, durasi, hasil).
- **Alert:** kegagalan pipeline & "laporan tidak terkirim pada jadwal" (watchdog §3.6).
- Jika Redis dipakai: **Laravel Horizon** untuk visibilitas queue.

---

## 5. Trade-off Analysis

| Keputusan | Dipilih | Alternatif | Mengapa & yang dikorbankan |
|---|---|---|---|
| Arsitektur | Modular monolith Laravel | Microservices | Skala kecil + tim Laravel → monolith paling cepat & murah. Korban: pemisahan rilis per-modul (tak dibutuhkan kini). |
| Sumber data | NEVIRA REST API | Direct DB / read-replica | Jaga batas & konsistensi; korban: agregasi sedikit lebih lambat (tidak material di skala ini). |
| Integrasi | Polling terjadwal | Webhook/event | Webhook NEVIRA belum pasti ada; polling cukup untuk laporan harian & cek diam. Desain event-ready bila berubah. |
| Queue | Redis (fallback DB) | Broker eksternal (SQS/Rabbit) | Reuse infra internal; broker eksternal overkill. |
| Pengiriman WA | Abstraksi 3 mode (hybrid→assisted→full_auto), cutover per target | Langsung full-auto Groups API | OBA memblokir Opsi A; hybrid memberi nilai lebih dulu & jadi periode validasi sebelum manusia dicabut (§3.8). Korban: langkah manual sementara + perlu approved template. |
| Orphaned production | Field terstruktur > heuristik | Hanya heuristik | Field lebih andal tapi perlu NEVIRA; heuristik sebagai cadangan. Korban: akurasi bergantung SOP. |
| Penyimpanan data | Output turunan saja | Cache penuh transaksi | Jaga "sumber tunggal" + minim PII. Korban: tiap run fetch ulang (murah di skala ini). |
| Template | Model konten + transport terpisah; builder drag & drop | Teks hardcode per outlet | Fleksibel & dapat diwarisi; korban: kompleksitas + harus muat approved Meta template untuk Opsi A. |
| Identitas | Auth OMS sendiri + hook SSO | Gabung ke LBE / hanya pakai NEVIRA | Role app tak ada di NEVIRA; LBE beda concern. Korban: identitas kedua sementara sampai SSO ERP siap. |

---

## 6. Yang Akan Ditinjau Ulang Saat Bertumbuh

- **>150 outlet / volume tinggi:** pindah queue ke Redis penuh + multi-worker; pertimbangkan read-replica NEVIRA untuk agregasi.
- **NEVIRA rilis webhook:** ganti poller dengan receiver event (sudah disiapkan) → cek outlet diam jadi mendekati real-time.
- **Kebutuhan multi-brand (KWL, Nevira-POS lain):** generalisasi `TransactionSource` untuk banyak sumber POS.
- **LBE matang:** dari endpoint read-only → kontrak data/ETL formal ke warehouse LBE.
- **Volume sinyal tinggi:** tambah dedup & prioritas; pertimbangkan UI triase khusus.

---

## 7. Dampak ke Breakdown Tiket

System design ini mempertajam beberapa tiket Modul 1 (lihat `Modul1_Engineering_Tickets.md`):

- OPS-101 (skema) → diperluas jadi tabel di §3.3 (tambah `cashier_input_scores`, `outlet_operating_hours`).
- OPS-102 (klien NEVIRA) → di balik interface `TransactionSource` (§3.2) agar event-ready & dapat ditukar.
- Tambahan implisit: **Watchdog anti silent-failure** (§3.6) dan **kebijakan minim-PII** (§3.6) sebaiknya jadi tiket eksplisit di Epic G.
- Urutan sprint tetap valid; Sprint 1 kini punya kontrak interface yang jelas sebelum domain lain dibangun.
