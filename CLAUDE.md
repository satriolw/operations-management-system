# CLAUDE.md — Operations Management System (OMS) · Apique Group (Modul 1)

> Letakkan file ini di **root repository kode** (bukan di folder dokumen). Ia dibaca otomatis oleh Claude Code setiap sesi sebagai konteks + aturan. Dokumen perencanaan ada di folder `Operations System Management/`.

## Apa ini

**Operations Management System (OMS)** — aplikasi operasional Apique Group / Less Worry / Kain Wangi. **Modul 1** = mesin laporan & sinyal + lapisan Admin: tiap hari menarik data dari **NEVIRA (POS)**, menyusun laporan harian untuk investor (template dinamis), mengirim via WhatsApp, menurunkan sinyal operasional (outlet diam, anomali void/refund), dan menyediakan Admin untuk mengelola outlet, akun/target WhatsApp, template, dan user. Berdiri sendiri sebagai aplikasi, tetapi **satu sumber data** dengan ERP dan output-nya dapat di-query **LBE**.

## Dokumen acuan (baca sebelum mengubah arsitektur)

- `Operations System Management/Apique_Operations_System_Blueprint_PRD.md` — produk & requirement.
- `Operations System Management/Modul1_System_Design.md` — arsitektur (sumber kebenaran teknis).
- `Operations System Management/Modul1_Engineering_Tickets.md` — tiket OPS-xxx + acceptance criteria.
- `Operations System Management/Modul1_Architecture_Review.md` — ADR + 6 risiko yang harus ditangani.

Jika permintaan bertentangan dengan dokumen ini, **berhenti dan tanyakan** — jangan berasumsi.

## Stack & konvensi

- PHP **Laravel** (modular monolith), **MySQL/MariaDB**, queue **Redis** (fallback DB), **Laravel Scheduler** untuk cron.
- Async lewat **Queue Jobs**; penjadwalan lewat **Scheduler**. Hindari kerja berat di request siklus web.
- Modul domain (namespace/folder terpisah): `Ingestion`, `Reporting`, `Revenue`, `Signals`, `Delivery`, `Templating`, `Admin`, `Identity`.
- Admin UI: paket auth/role (mis. `spatie/laravel-permission`); pertimbangkan Filament/Livewire untuk CRUD cepat. Template builder drag & drop di frontend (lihat aturan template di bawah).
- Test: Pest/PHPUnit. Tulis test **lebih dulu** dari implementasi bila memungkinkan.
- Commit kecil per tiket; PR memetakan ke satu OPS-xxx + checklist Definition of Done.

## ⛔ Aturan emas (jangan dilanggar)

1. **NEVIRA = sumber kebenaran tunggal, diakses via REST API saja.** Jangan baca DB NEVIRA langsung. Semua akses lewat interface `TransactionSource` (anti-corruption layer). Jangan menaruh klien HTTP konkret di domain.
2. **Jangan menyimpan kebenaran transaksi NEVIRA.** Persist hanya **output turunan** (status laporan, sinyal, koreksi revenue), dengan referensi `transaction_number`/`id_transaction`.
3. **Jangan menyimpan PII customer** (nama, telepon, alamat) dari record void/refund. Simpan hanya metadata sinyal (transaction_number, nominal, alasan, id_cashier, tanggal). Lihat OPS-705.
4. **Semua waktu dinormalkan ke `Asia/Jakarta` (WIB).** ⚠️ Jebakan nyata: timestamp **tingkat-transaksi** NEVIRA berformat WIB (`"2026-06-12 13:10:12"`), tetapi timestamp **nested `services`** berformat UTC (`"...06:10:12.000000Z"`) — beda 7 jam. Untuk logika tanggal (Penyesuaian Revenue), **pakai field tingkat-transaksi** dan normalkan eksplisit. Selalu uji batas tengah malam.
5. **Idempotency wajib.** Kunci per `(outlet, report_date)` dan `(report_run, channel)`. Re-run/replay tidak boleh menghasilkan kiriman ganda. "Tepat satu channel aktif per target per hari."
6. **Output ber-`id_outlet` + berstempel waktu**, skema bersih agar LBE bisa query (jangan kunci data hanya untuk app ini).
7. **Rahasia** (token NEVIRA, kredensial WhatsApp) di secret store / `.env`, **tidak** di-commit.

## NEVIRA — integrasi

Endpoint (lihat PRD §6.2):
- `GET /api/reports/dashboard?start_date=&end_date=&id_outlet=`
- `GET /api/transactions?status=VOID|REFUND&id_outlet=&start_date=&end_date=&is_void_refund=true`
- `GET /api/transactions?payment_status=UNPAID&id_outlet=&start_date=&end_date=` (untuk Terealisasi vs Piutang)

Catatan: response **paginated** (`per_page`, `next_page_url`, `last_page`) — kumpulkan semua halaman. Hormati `429` dengan backoff. Sebar jadwal antar-outlet (jangan serentak). Webhook NEVIRA **belum tentu ada** → desain poller event-ready (OPS-107 stub).

**Auth NEVIRA = login token 24 jam.** Token manager di `NeviraApiSource` (OPS-108): login dgn service credential → token 24 jam, cache bersama (Redis); refresh proaktif menjelang expiry; pada **401** single-flight re-login lalu retry sekali; gagal → alert + fallback. **401/403 bukan error transient** (jangan di-backoff seperti 429/5xx). Service credential di secret store, bukan hanya token.

Pemetaan field penting: `transaction_number`, `status`, `grand_total`, `created_at` (tgl nota, WIB), `approve_refund_void_date` (tgl disetujui, WIB), `refund_notes`/`void_notes` (alasan), `refund_void_by` (pemohon), `refund_void_approved_by` (penyetuju), `payment_status`, `id_cashier`, `progress_percentage`.

## Aturan domain yang gampang salah

- **Revenue = PAID + UNPAID (piutang).** `total_sales` NEVIRA memasukkan piutang B2B. Maka **VOID (unpaid) DAN REFUND (paid) sama-sama me-restate revenue** tanggal nota.
- **Penyesuaian Revenue:** cari void+refund dengan `approve_refund_void_date` = hari ini DAN `created_at` < hari ini, **lookback ~7 hari** (approval bisa telat & batch). Restate per tanggal nota.
- **KPI akurasi input:** atribusikan ke `id_cashier` (pembuat nota), BUKAN `refund_void_by`.
- **Self-approval (OPS-601):** flag `refund_void_by == refund_void_approved_by`, **tetapi hanya pelanggaran bila role penyetuju < Kepala Toko** (kebijakan). Butuh peta `id_role → level` (dependensi data — belum tersedia; sampai ada, catat sebagai "perlu ditinjau", jangan blokir).
- **Orphaned production (OPS-604):** void/refund pada `progress_percentage > 0` tanpa nota pengganti. Field "nota pengganti" NEVIRA **belum ada** → pakai **heuristik** (customer sama + item/nominal mirip + waktu berdekatan). Label "perlu ditinjau", bukan tuduhan.

## Delivery — 3 mode (System Design §3.8)

`hybrid` (draft ke Head Store, paste manual) → `assisted` (Head Store klik "Setujui & Kirim", app kirim ke grup via API) → `full_auto`. Mode disimpan **per target** (`delivery_targets.deliver_mode`), diedit lewat Admin (bukan kode). MVP = `hybrid` (tidak butuh OBA). Pindah ke assisted/full_auto lewat gerbang kesiapan (OPS-306): `group_id` valid + investor sudah join + approved template + OBA aktif. Fallback otomatis ke hybrid bila Groups API gagal.

## Template laporan — dinamis, master → override (System Design §3.9)

- **Pewarisan:** ada `report_templates` master (grup) yang diwarisi per outlet/target; outlet boleh **override** karena background investor berbeda.
- **Token:** blok mengikat ke field response NEVIRA (mis. `{{total_sales}}`, `{{realized}}`, `{{piutang}}`, `{{txn_count}}`, `{{penyesuaian_revenue}}`). Builder **drag & drop** menyusun urutan blok + token; simpan sebagai `layout_json`.
- ⚠️ **Batas transport (penting):** pesan bisnis-initiated ke grup WA WAJIB approved Meta template (variabel `{{1}}…`). Maka **pisahkan model konten dari transport**: render `layout_json` → teks bebas untuk `hybrid`; untuk `assisted`/`full_auto` isi ke **satu approved template fleksibel** (body 1 parameter besar). Validasi bahwa konten full_auto muat ke approved template sebelum kirim.
- **Pipeline jangan terblokir builder:** pipeline laporan memakai template apa pun yang aktif (seed default bila belum ada). Builder = cara manusia menyunting template, berjalan **paralel**, bukan prasyarat backend.

## Identity & user (System Design §3.10)

- OMS punya **auth + role/permission sendiri** untuk kebutuhan app (siapa boleh "Setujui & Kirim", review sinyal, admin outlet). **Jangan gabung ke LBE.**
- Sediakan **hook federasi**: interface `IdentityProvider` agar bisa **connect/SSO ke LBE/ERP** nanti tanpa rombak. Jangan duplikasi master data karyawan — referensikan saat SSO tersedia.
- Bedakan: **user login OMS** (identitas app) vs **aktor NEVIRA** (`id_cashier`/`id_role` pada transaksi, dipakai sinyal). Peta `id_role → level` (untuk OPS-601) berasal dari NEVIRA, bukan dari user OMS.

## Audit trail tinjauan (evidence)

Setiap tindak lanjut atas `signal_events` dan `revenue_adjustments` WAJIB mencatat **siapa (`reviewer_user_id`), kapan (`reviewed_at`), outcome, dan catatan**. Lampiran bukti opsional. Tujuan: "sudah ditinjau" harus dapat dibuktikan. Jangan jadikan workflow approval berlapis.

## Master data — CRUD

Semua master data punya CRUD di Admin: outlets (+ jam laporan, **jam cek outlet-diam dinamis**, ambang, jam operasional/libur), `whatsapp_accounts`, `delivery_targets`, `report_templates`, users/roles, investor (ringan, 1:1 outlet), dan peta referensi (mis. `id_role → level`, kalender libur). Tidak ada nilai hardcode untuk hal yang seharusnya dikonfigurasi.

> **Kredensial NEVIRA** di `.env`/secret store, **bukan** CRUD (satu kredensial stabil). `whatsapp_accounts` menyimpan **referensi** ke secret store (`credentials_ref`), bukan secret mentah.

## Edge cases & hardening (System Design §3.13)

Invariant: refund selalu penuh (`restate = grand_total`); transaksi tak bisa diedit langsung (Penyesuaian Revenue lengkap) — uji keduanya. Investor 1:1 outlet & **tidak login app** (terima laporan via WA); otorisasi per-outlet hanya untuk staf internal. Aturan penting: hybrid wajib **konfirmasi "sudah dikirim"** (watchdog pakai status ini); baseline outlet-diam dihitung **hanya dari hari buka & bertransaksi**; sinyal **severity-tiered** (high real-time, low digest) untuk hindari alert fatigue; **reviewer ≠ subjek**; nomor WA hilang butuh **DR** (recreate grup + re-invite, fallback hybrid); template master **berversi** (draft→preview→publish). KWL self-service = profil terpisah, di luar Modul 1.

## Cara kerja yang diharapkan (untuk Claude Code)

1. **Interface dulu, implementasi kemudian.** Bangun `TransactionSource` & `Deliverer` (kontrak) sebelum domain memakainya.
2. **Seed fixture dari data nyata.** Simpan response void/refund/unpaid contoh sebagai fixture test; tulis contract test + test batas tengah malam sebelum logika revenue.
3. **Satu modul / satu tiket per PR.** Urutan: Ingestion → Reporting → Revenue → Signals → Delivery.
4. **Definition of Done (tiap PR):** unit test lulus; tidak menyimpan kebenaran NEVIRA; tidak ada PII customer; waktu ter-normalisasi WIB; observability (log) terpasang; acceptance criteria tiket tercentang.
5. Bila menemukan ambiguitas atau asumsi baru, **catat & tanyakan** — jangan diam-diam memutuskan arsitektur.

## Perintah umum (sesuaikan saat repo dibuat)

```bash
php artisan migrate                 # skema output (OPS-101)
php artisan schedule:work           # scheduler lokal
php artisan queue:work              # worker queue
php artisan test                    # jalankan test
# Audit void/refund populasi penuh (skrip terpisah, jalankan dgn token NEVIRA):
NEVIRA_TOKEN=... START_DATE=... END_DATE=... php "Operations System Management/audit_void_refund.php"
```

## Dependensi eksternal (di luar kode — jangan diasumsikan beres)

- **OBA** (verifikasi WhatsApp Business) — memblokir Opsi A. Nomor baru sedang dibeli.
- **Approved Groups template** Meta — item ber-lead-time, urus paralel OBA.
- **Peta `id_role → level`** NEVIRA — dibutuhkan OPS-601.
- **Field "nota pengganti"** NEVIRA — akan ada tapi tidak segera; pakai heuristik dulu.
- **Konfirmasi teknis NEVIRA:** apakah ada webhook, berapa rate limit, engine DB (asumsi MySQL). Auth = login token 24 jam (RESOLVED 16 Juni 2026 → OPS-108).
