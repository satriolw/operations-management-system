# Modul 1 — Report & Signal Engine: Engineering Ticket Breakdown

**Aplikasi:** Operations Management System (OMS) · Apique Group
**Sumber:** Operations Management System Blueprint (PRD) v1.2 + `Modul1_System_Design.md` v1.2
**Scope:** Modul 1 saja (laporan harian otomatis + sinyal operasional). Modul 2 & 3 di luar dokumen ini.
**Stack:** PHP/Laravel, modular monolith, MySQL, queue Redis (fallback DB), Laravel Scheduler. Lihat system design untuk detail arsitektur.
**Estimasi:** story points Fibonacci (1, 2, 3, 5, 8, 13). 1 SP ≈ setengah hari kerja senior.

## Konvensi

- **Prioritas:** P0 = wajib untuk rilis Modul 1; P1 = fast-follow setelah P0 stabil.
- **MVP (Fase 1, hybrid):** tiket bertanda 🟢 cukup untuk merilis nilai tanpa menunggu OBA (laporan dikirim ke Head Store untuk paste manual).
- **Fase 2 (Opsi A):** tiket bertanda 🔵 mengaktifkan kirim otomatis ke grup investor via Groups API; bergantung OBA + migrasi grup.
- **Definition of Done (global):** kode + unit test lulus; di-review; observability (log + metrik) terpasang; tidak menyimpan salinan kebenaran transaksi (hanya output turunan); skema output dapat di-query LBE.

---

## Ringkasan Epic

| Epic | Judul | Tiket | Total SP |
|---|---|---|---|
| A | Fondasi & Integrasi NEVIRA | OPS-101…108 | 40 |
| B | Laporan Harian Otomatis | OPS-201…206 | 27 |
| C | Pengiriman WhatsApp | OPS-301…307 | 42 |
| D | Penyesuaian Revenue Engine | OPS-401…404 | 21 |
| E | Deteksi Outlet Diam | OPS-501…503 | 13 |
| F | Sinyal Anomali & Integritas | OPS-601…606 | 33 |
| G | Observability & Reliability | OPS-701…706 | 22 |
| H | Admin & Master Data CRUD | OPS-801…805 | 34 |
| I | Template Engine (dinamis + drag & drop) | OPS-901…903 | 29 |
| J | Edge Cases & Hardening | OPS-1001…1005 | 21 |
| | **Total** | | **~282 SP** |

> Catatan jalur kritis MVP (🟢): laporan hybrid kini juga butuh **Admin minimal** (auth OPS-801, CRUD outlet/target OPS-803/804, scoping per-outlet OPS-1003) dan **template** (model + render hybrid: OPS-901/903). Mayoritas Epic J adalah **P1 hardening** (fast-follow), bukan pemblokir rilis. Builder drag & drop visual (OPS-902) dan Opsi A penuh (🔵) menyusul paralel — pipeline jalan dengan template seed default lebih dulu.

---

## Garis Rilis: v1 vs Fast-Follow

**Definisi v1:** versi terkecil yang memberi nilai nyata — **laporan harian hybrid yang akurat ke investor + monitoring outlet-diam dasar**, lengkap dengan fondasi (data, auth, scoping, observability). v1 sengaja **tidak** menyertakan Opsi A (Groups API), builder drag & drop visual, dan sinyal integritas lanjutan — semua itu fast-follow yang berjalan **setelah** v1 terbukti dipakai.

**v1 (≈175 SP) — wajib untuk rilis pertama:**

| Epic | Tiket v1 |
|---|---|
| A · Fondasi | OPS-101, 102, 103, 104, 105, 106, 108 |
| B · Laporan | OPS-201, 202, 203, 204\*, 206 |
| C · Delivery (hybrid) | OPS-301, 302, 305 |
| D · Revenue | OPS-401, 402, 403 |
| E · Outlet diam | OPS-501, 502, 503 |
| G · Observability | OPS-701, 702, 704, 705 |
| H · Admin | OPS-801, 802, 803, 804\*\* |
| I · Template | OPS-901, 903 |
| J · Hardening inti | OPS-1001, 1003, 1005 |

\* **OPS-204 (render gambar dashboard)** = item v1 dengan **ketidakpastian tertinggi** (PHP lemah untuk render gambar — R2). Putuskan caranya di awal; bila mahal, laporan teks bisa rilis duluan dan gambar menyusul.
\*\* **OPS-804** v1 hanya bagian **delivery target** (rute ke Head Store); pengelolaan akun WA API/OBA menyusul dengan Opsi A.

**Fast-follow (≈107 SP) — setelah v1:**

| Urutan | Tiket | Kenapa ditunda |
|---|---|---|
| FF-1 (integritas) | OPS-601, 602, 603, 604, 605, 606, 1002, 805 | Sinyal anomali + severity tiering + audit trail; butuh tuning & peta `id_role→level`. |
| FF-2 (builder & template) | OPS-902, 1004 | Drag & drop visual + versioning; pipeline jalan dengan seed/override dulu. |
| FF-3 (Opsi A penuh) | OPS-303, 304, 306, 307, 706, 107 | Kirim otomatis ke grup; menunggu OBA + migrasi grup. |
| FF-4 (penyempurnaan) | OPS-205, 404, 703 | Narasi dinamis, rekonsiliasi laporan, backfill/replay. |

> Prinsip: setelah v1 rilis, **observasi penggunaan nyata** menentukan urutan fast-follow — jangan kunci FF terlalu dini.

---

## EPIC A — Fondasi & Integrasi NEVIRA

Tujuan: lapisan data bersih, klien NEVIRA, penjadwal. Semua epic lain bergantung pada ini.

### OPS-101 · Skema data output (LBE-ready) 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** —
Rancang & migrasikan skema relasional penuh sesuai System Design §3.3 untuk menyimpan *output turunan* (bukan salinan transaksi): `outlets`, `outlet_baselines`, `report_runs`, `report_deliveries`, `revenue_adjustments`, `signal_events`, `cashier_input_scores`. (`outlet_operating_hours` didefinisikan bersama OPS-106.) Berstempel waktu, beridentitas `id_outlet`, siap di-query LBE.
**Acceptance criteria:**
- [ ] ERD sesuai System Design §3.3 disepakati; migrasi Laravel dibuat & reversible.
- [ ] Indeks terpasang: `report_runs(id_outlet, report_date)`, `signal_events(id_outlet, type, detected_at)`, `revenue_adjustments(restated_for_date)`.
- [ ] Tidak ada tabel yang menduplikasi kebenaran transaksi NEVIRA (hanya referensi `transaction_number`/`id_transaction` + nilai turunan).
- [ ] Tidak menyimpan PII customer (lihat OPS-705); konvensi nama & tipe direview bersama owner LBE.

### OPS-102 · Klien NEVIRA di balik interface `TransactionSource` 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** —
Definisikan interface `TransactionSource` (System Design §3.2) dan implementasi `NeviraApiSource` (Laravel `Http`) untuk `/reports/dashboard`, `/transactions` (void/refund & unpaid), dengan auth token, retry/backoff, paginasi (`per_page`, `last_page`). Sumber disembunyikan di balik interface agar **event-ready** (bisa ditukar webhook/replica tanpa menyentuh domain lain).
**Acceptance criteria:**
- [ ] Interface `TransactionSource` dipakai oleh semua domain (Reporting/Revenue/Signals), bukan klien konkret.
- [ ] Token disimpan aman (secret store), tidak hardcoded.
- [ ] Paginasi otomatis mengumpulkan semua halaman; hormati 429 dengan backoff.
- [ ] **401/403 diserahkan ke token lifecycle (OPS-108), bukan di-retry sebagai transient**; hanya 429/5xx yang pakai backoff.
- [ ] Contract test terhadap response nyata (fixture void/refund/unpaid yang sudah ada).

### OPS-103 · Model & parser response transaksi 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-102
Petakan field NEVIRA ke model internal (lihat tabel pemetaan PRD §6.2): `transaction_number`, `status`, `grand_total`, `created_at`, `approve_refund_void_date`, `refund_notes`/`void_notes`, `refund_void_by`, `refund_void_approved_by`, `payment_status`, `id_cashier`, `progress_percentage`.
**Acceptance criteria:**
- [ ] Parser tahan terhadap field null (mis. `void_notes` null saat refund).
- [ ] Unit test memakai fixture void & refund nyata.

### OPS-104 · Kerangka penjadwalan (scheduler) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-101
Penjadwal job harian/jam-an per outlet (cron-like), idempoten, tahan retry, dengan timezone Asia/Jakarta.
**Acceptance criteria:**
- [ ] Job dapat dijadwalkan per-outlet pada jam berbeda.
- [ ] Re-run job yang sama tidak menghasilkan kiriman ganda (idempotency key).
- [ ] Kegagalan job tercatat & dapat di-retry manual.

### OPS-105 · Registry outlet & konfigurasi 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-101
Tabel konfigurasi outlet: `id_outlet`, nama, jam laporan, tujuan WhatsApp (investor/Head Store), aktif/non-aktif.
**Acceptance criteria:**
- [ ] CRUD konfigurasi (admin).
- [ ] Laporan & sinyal hanya jalan untuk outlet aktif.

### OPS-106 · Kalender operasional per outlet 🟢
**Prioritas:** P1 · **Estimasi:** 8 · **Blok:** OPS-105
Jam buka/tutup & hari libur per outlet untuk meredam false alarm "outlet diam". (P1 karena deteksi outlet diam bisa rilis dengan ambang konservatif dulu.)
**Acceptance criteria:**
- [ ] Model `outlet_operating_hours` (jam buka/tutup + libur per outlet).
- [ ] Deteksi outlet diam (OPS-501) menghormati kalender ini.

### OPS-107 · Penerima webhook NEVIRA (disiapkan, event-ready) 🔵
**Prioritas:** P2 · **Estimasi:** 3 · **Blok:** OPS-102
Stub `POST /webhooks/nevira` yang menulis event ke queue yang sama dengan poller — diaktifkan hanya bila NEVIRA menyediakan webhook (saat ini status tak diketahui). Membuat cek outlet-diam mendekati real-time tanpa rombak domain.
**Acceptance criteria:**
- [ ] Endpoint memvalidasi signature/secret; menulis ke queue lewat `TransactionSource` event path.
- [ ] Non-aktif by default (feature flag); tidak memengaruhi jalur polling.
- [ ] Terdokumentasi sebagai opsional — bergantung konfirmasi kapabilitas NEVIRA.

### OPS-108 · Token lifecycle & re-auth NEVIRA (login 24 jam) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-102
NEVIRA pakai **login token berlaku 24 jam** (dikonfirmasi 16 Juni 2026). Token manager di dalam `NeviraApiSource`:
- **Re-auth = panggil login endpoint** dengan service credential (disimpan di secret store — bukan cuma token).
- **Token cache bersama** (mis. Redis) + waktu perolehan; dipakai semua worker.
- **Proaktif:** refresh bila umur token mendekati 24 jam (mis. ≥23 jam) atau saat awal run.
- **Reaktif (401):** single-flight re-login (satu proses, request lain menunggu) → retry request asli **sekali** → bila gagal, **alert + fallback, berhenti** (tidak loop).
- 401/403 **tidak** diperlakukan sebagai error transient (bukan jalur backoff 429/5xx).

**Acceptance criteria:**
- [ ] Token kedaluwarsa → re-login otomatis, job lanjut tanpa intervensi manual.
- [ ] Re-login terjadi **sekali** walau banyak worker bersamaan (single-flight / lock).
- [ ] Kegagalan re-auth memunculkan alert jelas + fallback (bukan retry tak hingga / bukan silent).
- [ ] Service credential di secret store; token cache ber-TTL; tidak ada kredensial plaintext di log.

---

## EPIC B — Laporan Harian Otomatis

### OPS-201 · Ambil & agregasi data dashboard harian 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-102
Tarik `/reports/dashboard` per outlet per tanggal; ekstrak total_sales, avg_transaction, avg_customer_spending, unit_volumes, dll.
**Acceptance criteria:**
- [ ] Angka cocok dengan response NEVIRA untuk tanggal & outlet uji.
- [ ] Jumlah transaksi diturunkan konsisten (total ÷ avg, atau dari order_type_summary).

### OPS-202 · Split Terealisasi vs Piutang (P0-7) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-201
Hitung revenue paid vs unpaid memakai `?payment_status=UNPAID`; tampilkan dua baris yang jumlahnya = total_sales.
**Acceptance criteria:**
- [ ] Piutang = Σ grand_total unpaid pada outlet+tanggal; Terealisasi = total_sales − piutang.
- [ ] Jika tidak ada unpaid, baris piutang = Rp0 (atau disembunyikan sesuai keputusan UX).
- [ ] Unit test dengan fixture unpaid nyata (mis. outlet 117).

### OPS-203 · Renderer pesan laporan (hide-zero) (P0-1, P0-3) 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-201
Susun teks laporan profesional sesuai template PRD §8.3; sembunyikan metrik bernilai 0 (M², Pasang, Lembar).
**Acceptance criteria:**
- [ ] Output cocok dengan template; baris metrik 0 tidak muncul.
- [ ] Mendukung placeholder nama outlet, investor, tanggal (locale id-ID, format Rupiah).

### OPS-204 · Renderer gambar dashboard (mobile view) (P0-1) 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-201
Render kartu dashboard mobile (lihat mockup) menjadi gambar PNG di server untuk dilampirkan ke WhatsApp.
**Acceptance criteria:**
- [ ] PNG ter-generate dengan angka dari OPS-201/202.
- [ ] Ukuran/format sesuai untuk lampiran media WhatsApp.
- [ ] Render deterministik (uji snapshot).

### OPS-205 · Catatan naratif dinamis 🟢→P1
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-201
Kalimat CATATAN otomatis: positif bila penjualan di atas rata-rata bulan; netral-jujur bila di bawah (tidak dibesar-besarkan).
**Acceptance criteria:**
- [ ] Pemilihan kalimat berbasis pembanding rata-rata bulan berjalan.
- [ ] Tidak ada klaim berlebihan saat performa turun.

### OPS-206 · Rakit laporan final (compose) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-203, OPS-204, OPS-401
Gabungkan teks + blok Penyesuaian Revenue (bila ada) + gambar jadi satu payload laporan siap kirim; simpan ke `report_run`.
**Acceptance criteria:**
- [ ] Blok Penyesuaian Revenue tampil hanya bila ada koreksi (dari OPS-401).
- [ ] Payload tersimpan & dapat di-preview sebelum kirim.

---

## EPIC C — Pengiriman WhatsApp

### OPS-301 · Abstraksi channel pengiriman (3 mode) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-206
Antarmuka `Deliverer` mendukung tiga mode (System Design §3.8): `hybrid`, `assisted` (CloudApi + `requires_approval`), `full_auto`. Mode dikonfigurasi **per target** (per investor/grup), bukan global.
**Acceptance criteria:**
- [ ] Satu interface; mode dipilih via `deliver_mode` per target.
- [ ] Aturan "tepat satu channel aktif per target per hari" (idempotency saat ganti mode → tidak dobel kirim).
- [ ] Rantai fallback: kegagalan `CloudApiDeliverer` jatuh balik ke `hybrid` untuk kiriman itu.

### OPS-302 · Mode hybrid: kirim draft + konfirmasi kirim (P0-2) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-301
Kirim laporan siap-tempel ke Head Store untuk di-paste manual ke grup investor. **Head Store wajib menekan "Sudah saya kirim"** agar pengiriman ke investor terverifikasi (menutup celah: app hanya tahu draft sampai Head Store, bukan sampai investor).
**Acceptance criteria:**
- [ ] Head Store menerima teks + gambar pada jam terjadwal.
- [ ] Status berbeda: "draft terkirim ke Head Store" vs "dikonfirmasi terkirim ke investor" (oleh Head Store).
- [ ] Watchdog (OPS-704) memakai status **konfirmasi**, bukan status draft.

### OPS-303 · Integrasi WhatsApp Cloud API (Groups) + mode assisted 🔵
**Prioritas:** P0 (Fase 2) · **Estimasi:** 8 · **Blok:** OPS-301
Integrasi resmi: kirim `recipient_type:"group"` via Messages API; kelola approved template khusus Groups. Dukung flag `requires_approval` (mode `assisted`): app menyiapkan kiriman, menunggu aksi "Setujui & Kirim" dari Head Store, baru app yang mengirim ke grup.
**Acceptance criteria:**
- [ ] Kirim teks + media ke `group_id` berhasil; webhook status (sent/failed) tertangani.
- [ ] Approved Groups template terdaftar & dipakai; renderer laporan menghasilkan konten yang muat ke template (isi identik dgn hybrid).
- [ ] Mode `assisted`: kiriman hanya keluar setelah persetujuan satu-klik; tercatat siapa & kapan menyetujui.
- [ ] Prasyarat OBA terpenuhi (lihat dependensi non-teknis).

### OPS-304 · Pembuatan & migrasi grup via Groups API 🔵
**Prioritas:** P0 (Fase 2) · **Estimasi:** 8 · **Blok:** OPS-303
Buat grup per investor via API (maks 8 peserta), generate invite link, kelola onboarding investor sekali.
**Acceptance criteria:**
- [ ] Grup terbuat terprogram; invite link terkirim; peserta tergabung.
- [ ] Mapping outlet/investor → `group_id` tersimpan; ekspos flag `group_ready` (cek peserta mengandung investor) yang dipakai gerbang cutover OPS-306.
- [ ] Runbook migrasi grup existing → grup-API terdokumentasi.

### OPS-305 · Status pengiriman & retry (P0-2) 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-302
Catat status terkirim/gagal; retry terjadwal; alert internal bila gagal setelah N percobaan.
**Acceptance criteria:**
- [ ] Setiap kiriman punya status final (sent/failed) + timestamp.
- [ ] Kegagalan persisten memunculkan alert ke ops.

### OPS-306 · Orkestrasi cutover (gerbang kesiapan + canary) 🔵
**Prioritas:** P0 (Fase 2) · **Estimasi:** 5 · **Blok:** OPS-303, OPS-304
Kelola perpindahan per target hybrid → assisted → full_auto sesuai System Design §3.8.
**Acceptance criteria:**
- [ ] Cutover ke assisted/full_auto diblokir sampai precondition lulus: `group_id` valid, investor sudah join grup, approved template tersedia, kredensial OBA aktif.
- [ ] Mode dapat diubah per target (canary 1 investor → sisanya) tanpa deploy ulang.
- [ ] Transisi tidak menyebabkan kiriman ganda; fallback ke hybrid bila Groups API gagal.

### OPS-307 · Pemulihan (DR) nomor/akun WhatsApp 🔵
**Prioritas:** P1 · **Estimasi:** 8 · **Blok:** OPS-804, OPS-304
Tangani nomor hilang/banned: nomor OBA yang "memiliki" grup-API tidak bisa sekadar diganti. State akun `active/lost/recovering`; runbook re-provision → OBA → recreate grup → re-invite investor; selama transisi fallback otomatis ke hybrid.
**Acceptance criteria:**
- [ ] State akun WA terlacak; "lost" memicu fallback hybrid untuk target terdampak.
- [ ] Runbook pemulihan terdokumentasi & teruji (recreate grup + re-invite).
- [ ] Tidak ada laporan yang hilang selama masa pemulihan (jatuh ke hybrid).

---

## EPIC D — Penyesuaian Revenue Engine

### OPS-401 · Deteksi koreksi cross-day (P0-4) 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-103
Query VOID + REFUND dengan jendela lookback ~7 hari; filter `approve_refund_void_date` = hari ini DAN `created_at` < hari ini; hasilkan daftar koreksi (nominal, alasan, tanggal nota).
**Acceptance criteria:**
- [ ] Menangkap void & refund yang disetujui hari ini atas nota hari lampau.
- [ ] Tidak menghitung ganda; idempoten per tanggal laporan.
- [ ] Unit test: kasus same-day (tidak masuk), cross-day (masuk), batch-approval (semua masuk).

### OPS-402 · Restate revenue per tanggal nota (P0-4) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-401
Hitung revenue lama → baru per tanggal nota terdampak; simpan ke `revenue_adjustment`.
**Acceptance criteria:**
- [ ] Total penyesuaian & revenue ter-restate benar (uji dengan contoh Rp582.560 dari audit).
- [ ] Mencakup void (unpaid) maupun refund (paid) sesuai aturan akuntansi PRD §8.4.

### OPS-403 · Renderer blok Penyesuaian Revenue (P0-4) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-402
Render blok opsional sesuai template PRD §8.4 (nota, jenis, nominal, alasan, total, revenue lama→baru). Tidak tampil bila kosong.
**Acceptance criteria:**
- [ ] Alasan diambil dari `refund_notes`/`void_notes`.
- [ ] Blok absen total bila tidak ada koreksi.

### OPS-404 · Rekonsiliasi dengan laporan terkirim sebelumnya 🟢→P1
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-402
Tandai bila tanggal yang di-restate sudah pernah dilaporkan ke investor (agar narasinya jelas "koreksi atas laporan tanggal X").
**Acceptance criteria:**
- [ ] Cross-check terhadap `report_run` historis.

---

## EPIC E — Deteksi Outlet Diam

### OPS-501 · Hitung baseline transaksi per outlet (P0-5) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-102
Hitung baseline jumlah transaksi per outlet per jam (rata-rata 30 hari) untuk titik cek.
**Acceptance criteria:**
- [ ] Baseline tersimpan di `outlet_baseline`, diperbarui berkala.
- [ ] Baseline dihitung **hanya dari hari buka & bertransaksi** (buang hari libur/tutup/nol agar ambang tidak bias).
- [ ] Robust terhadap outlet baru (data < 30 hari → fallback ambang konservatif).

### OPS-502 · Job cek outlet diam + alert (P0-5) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-501, OPS-104
Pada titik cek **yang dikonfigurasi per outlet** (dari OPS-803, bukan hardcode), bandingkan realisasi vs baseline; alert bila < ambang (dapat diatur).
**Acceptance criteria:**
- [ ] Outlet tanpa transaksi pada jam wajar memicu alert < 60 menit.
- [ ] Hari libur/tutup (OPS-106) tidak memicu alarm.
- [ ] Alert tersimpan di `signal_event` + terkirim ke Head Store/Area Manager.

### OPS-503 · Saluran & dedup alert 🟢
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-502
Cegah alert berulang untuk kondisi yang sama dalam satu hari; satukan pengiriman.
**Acceptance criteria:**
- [ ] Maksimal 1 alert "diam" per outlet per titik cek per hari.

---

## EPIC F — Sinyal Anomali & Integritas

### OPS-601 · Flag self-approval — sadar-kebijakan (P1, prioritaskan) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-103
Tandai void/refund dengan `refund_void_by` = `refund_void_approved_by`, **tetapi hanya melanggar bila level role penyetuju di BAWAH Kepala Toko (Head Store).** Kebijakan (dikonfirmasi 12 Juni 2026): wewenang ganda request+approve diizinkan untuk level karyawan ≥ Kepala Toko. Perlu sumber referensi hierarki role (peta `id_role` → level) — lihat dependensi non-teknis.
**Acceptance criteria:**
- [ ] Self-approval oleh role < Kepala Toko → `signal_event` severity tinggi.
- [ ] Self-approval oleh role ≥ Kepala Toko → dicatat sebagai pengecualian sah (audit trail), bukan pelanggaran.
- [ ] Uji dengan kasus nyata (user 181, refund 8134 & 7971) setelah level role-nya diketahui.

### OPS-602 · Flag batch-approval (P1) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-103
Tandai >N persetujuan oleh approver sama dalam jendela waktu pendek (mis. >2 dalam 60 detik).
**Acceptance criteria:**
- [ ] Uji dengan kasus nyata (user 180, 4 void @ 9 Juni 11:58).
- [ ] Ambang dapat dikonfigurasi.

### OPS-603 · KPI akurasi input per kasir (P1) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-103
Hitung rate void/refund berkategori "salah input" per kasir; **attribution ke `id_cashier`**, bukan `refund_void_by`.
**Acceptance criteria:**
- [ ] Klasifikasi alasan (taksonomi: input error / permintaan-ubah / belum bayar).
- [ ] Atribusi ke pembuat nota; uji kasus requester ≠ cashier (6/20 di sampel).
- [ ] Hasil tersimpan di `cashier_input_scores` (System Design §3.3).

### OPS-604 · Flag orphaned production (P1) 🟢
**Prioritas:** P1 · **Estimasi:** 8 · **Blok:** OPS-103
Tandai void/refund pada order `progress_percentage` > 0 tanpa nota pengganti. **Status field NEVIRA "nota pengganti" (dikonfirmasi 12 Juni 2026): akan disediakan TAPI tidak dalam waktu dekat.** Untuk Modul 1 pakai **heuristik sebagai mode utama** (customer sama + item/nominal mirip + dibuat berdekatan setelah void); siapkan abstraksi untuk beralih ke field terstruktur saat tersedia. Label "perlu ditinjau".
**Acceptance criteria:**
- [ ] Heuristik rekonsiliasi berjalan & dapat dikalibrasi (jendela waktu, kemiripan item/nominal).
- [ ] Abstraksi siap menerima field terstruktur tanpa rombak saat NEVIRA menyediakannya.
- [ ] Alert berlabel "perlu ditinjau" (bukan "kebocoran terkonfirmasi").
- [ ] Uji kasus 6003 (produksi 100% lalu void).

### OPS-605 · Aging piutang (P1) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-202
Daftar order UNPAID melewati X hari, belum dibayar & belum di-void.
**Acceptance criteria:**
- [ ] Threshold umur dapat dikonfigurasi.
- [ ] Output dapat di-query LBE & muncul di rekap internal.

### OPS-606 · Audit trail tinjauan (evidence) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-101, OPS-801
Catat bukti bahwa sinyal & penyesuaian revenue benar-benar ditinjau (System Design §3.11). Tabel `review_logs` + status pada `signal_events`/`revenue_adjustments`.
**Acceptance criteria:**
- [ ] Setiap aksi tinjauan menyimpan `reviewer_user_id`, `reviewed_at`, `outcome`, dan catatan (wajib); lampiran bukti opsional.
- [ ] **Reviewer ≠ subjek**: orang yang jadi subjek sinyal (mis. `refund_void_by`/`approved_by`) tidak boleh menutup sinyalnya sendiri; eskalasi bila Head Store terlibat.
- [ ] Riwayat tinjauan tak dapat diubah diam-diam (append-only / ter-audit).
- [ ] UI tinjauan menampilkan jejak siapa/kapan/kenapa; bukan workflow approval berlapis.

---

## EPIC G — Observability & Reliability

### OPS-701 · Logging & metrik terstruktur 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-104
Log terstruktur untuk tiap job (fetch, render, deliver) + metrik (sukses/gagal, latensi, jumlah kiriman).
**Acceptance criteria:**
- [ ] Dashboard metrik dasar; korelasi per outlet/tanggal.

### OPS-702 · Alerting kegagalan pipeline 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-701
Alert internal bila job gagal / laporan tidak terkirim pada jadwal.
**Acceptance criteria:**
- [ ] Kegagalan kirim memicu notifikasi ke tim ops/eng.
- [ ] "Laporan tidak terkirim pada jam X" terdeteksi (bukan silent failure).

### OPS-703 · Backfill & replay 🟢→P1
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-104
Jalankan ulang laporan/sinyal untuk tanggal lampau (mis. setelah perbaikan bug) tanpa kiriman ganda.
**Acceptance criteria:**
- [ ] Mode "preview/dry-run" tanpa kirim.
- [ ] Replay idempoten.

### OPS-704 · Watchdog anti silent-failure 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-206, OPS-305
Job verifikasi: setiap outlet aktif harus punya `report_delivery` sukses dalam jendela jadwalnya. Bila tidak → alert. Menutup NFR "kegagalan terdeteksi, bukan silent" (System Design §3.6).
**Acceptance criteria:**
- [ ] Outlet yang laporannya tidak terkirim pada jadwal memicu alert (bukan diam).
- [ ] Watchdog sendiri dimonitor (tidak ikut gagal diam-diam).

### OPS-705 · Kebijakan minim-PII & retensi 🟢
**Prioritas:** P0 · **Estimasi:** 3 · **Blok:** OPS-101
Pastikan output DB tidak menyimpan PII customer (nama, telepon) dari record void/refund — hanya metadata sinyal (transaction_number, nominal, alasan, id_cashier, tanggal). Tetapkan retensi data turunan (System Design §3.6). Termasuk: **service credential NEVIRA untuk re-auth (OPS-108) disimpan di secret store**, bukan di DB/repo.
**Acceptance criteria:**
- [ ] Tidak ada kolom/serialisasi yang mempersist PII customer.
- [ ] Service credential NEVIRA & referensi kredensial WhatsApp di secret store; tidak ada secret mentah di DB/log.
- [ ] Kebijakan retensi (mis. purge payload mentah setelah N hari) terdokumentasi & terimplementasi.
- [ ] Review privasi lulus sebelum rilis.

### OPS-706 · WhatsApp cost guard 🔵
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-303
Per-message pricing → bug loop bisa membengkak tagihan. Counter pesan + cap/alert budget harian + circuit breaker.
**Acceptance criteria:**
- [ ] Jumlah pesan terkirim per hari/akun terpantau.
- [ ] Ambang anomali (mis. >X pesan/jam) memicu alert + circuit breaker (jeda pengiriman otomatis).

---

## EPIC H — Admin & Master Data CRUD

Lapisan Admin: semua master data dapat dikelola lewat UI, tidak ada nilai hardcode.

### OPS-801 · Auth + Role/Permission + hook SSO 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** —
Auth OMS sendiri (mis. `spatie/laravel-permission`) untuk role app: admin, head_store, area_manager, ops. Sediakan interface `IdentityProvider` agar bisa **connect/SSO ke LBE/ERP** nanti tanpa rombak (System Design §3.10).
**Acceptance criteria:**
- [ ] Login + role/permission; gate aksi sensitif (Setujui & Kirim, review sinyal, edit master data).
- [ ] `IdentityProvider` mengabstraksi sumber identitas (lokal sekarang; SSO LBE kemudian).
- [ ] Tidak menduplikasi master data karyawan saat SSO aktif (referensi, bukan salin).

### OPS-802 · CRUD User & Role 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-801
Halaman kelola user, assign role/permission.
**Acceptance criteria:**
- [ ] CRUD user; assign/cabut role; non-aktifkan user.

### OPS-803 · CRUD Outlet (jam cek diam dinamis) 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-105, OPS-106
Halaman edit outlet: jam laporan, **jam-jam cek outlet diam (banyak, per outlet, dapat diubah)**, ambang, jam operasional & libur. Memindahkan checkpoint dari hardcode ke konfigurasi.
**Acceptance criteria:**
- [ ] Tambah/edit/hapus titik cek outlet-diam per outlet; OPS-502 membacanya saat runtime.
- [ ] Edit jam laporan, ambang, jam operasional/libur; perubahan berlaku tanpa deploy.

### OPS-804 · CRUD Akun WhatsApp & Delivery Target 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-301
Kelola `whatsapp_accounts` (nomor/akun, status OBA, kredensial-ref) dan `delivery_targets` (outlet→investor, channel, group_id, `deliver_mode`, template). Pengiriman **dinamis & dapat diubah sewaktu-waktu**.
**Acceptance criteria:**
- [ ] CRUD akun WA & target; ubah `deliver_mode` per target tanpa deploy.
- [ ] Perpindahan ke assisted/full_auto tetap melewati gerbang kesiapan (OPS-306); ganti nomor/target memicu re-validasi.
- [ ] Kredensial tidak tampil plaintext; tersimpan di secret store.

### OPS-805 · CRUD master data referensi 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-801
CRUD untuk master data sisanya: peta `id_role → level` (untuk OPS-601), kalender libur, dan referensi lain.
**Acceptance criteria:**
- [ ] Semua referensi yang dipakai logika domain dapat dikelola via UI (tidak ada hardcode tersembunyi).

---

## EPIC I — Template Engine (dinamis + drag & drop)

### OPS-901 · Model template + pewarisan + token 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-101, OPS-103
`report_templates` (master grup → override per outlet/target), blok ber-token terikat field response NEVIRA (`{{total_sales}}`, `{{realized}}`, `{{piutang}}`, `{{penyesuaian_revenue}}`, dst.), disimpan sebagai `layout_json` (System Design §3.9).
**Acceptance criteria:**
- [ ] Master template dapat diwarisi; outlet/target boleh override.
- [ ] Token tervalidasi terhadap field response yang tersedia; seed satu template default.

### OPS-902 · Builder drag & drop + live preview 🟢
**Prioritas:** P0 · **Estimasi:** 13 · **Blok:** OPS-901
UI menyusun blok/token via drag & drop, atur urutan, edit wording, **preview langsung dari sample response** outlet. Simpan sebagai master atau override.
**Acceptance criteria:**
- [ ] Drag & drop blok/token; reorder; preview render real-time dgn data contoh.
- [ ] Simpan/duplikasi sebagai master atau override per outlet/target.
- [ ] Tidak memblokir pipeline: pipeline tetap jalan dgn template aktif/seed bila builder belum dipakai.

### OPS-903 · Rendering + pemetaan transport 🟢
**Prioritas:** P0 · **Estimasi:** 8 · **Blok:** OPS-901
Render `layout_json` → output. **Pemisahan transport (kritis):** `hybrid` = teks bebas; `assisted`/`full_auto` = isi bagian variabel ke **approved Meta template fleksibel** (body 1 parameter besar). Validasi konten full_auto muat ke approved template sebelum kirim.
**Acceptance criteria:**
- [ ] Render konsisten untuk hybrid (teks) & Opsi A (template params).
- [ ] Guard: kirim full_auto ditolak bila konten tak muat approved template → fallback hybrid + alert.
- [ ] Angka nol disembunyikan; format Rupiah & tanggal locale id-ID.

---

## EPIC J — Edge Cases & Hardening (v1.2)

Dari challenge edge-case. Sebagian besar **P1 (fast-follow)** — bukan pemblokir rilis pertama. Detail di System Design §3.13.

### OPS-1001 · Empty-state & hari tutup pada laporan 🟢
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-206, OPS-106
Outlet **buka tapi nol transaksi → tetap kirim** laporan dengan catatan jujur ("belum ada transaksi hingga pukul X") + alert internal. Outlet **tutup/libur → suppress** atau beri catatan (tidak kirim "Rp0" polos).
**Acceptance criteria:**
- [ ] Hari buka-nol: laporan terkirim dengan framing + memicu sinyal outlet diam.
- [ ] Hari tutup/libur (dari kalender): laporan disuppress/diberi catatan, tidak alarm palsu.

### OPS-1002 · Severity tiering & digest sinyal 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-601…605
Cegah alert fatigue (75% sinyal = input error rutin). High → real-time; low → digest.
**Acceptance criteria:**
- [ ] Tiap tipe sinyal punya severity; high (self-approval pelanggaran, void nominal besar, orphaned) real-time.
- [ ] Low (input error rutin) masuk **digest harian/mingguan**, bukan notifikasi per kejadian.

### OPS-1003 · Otorisasi per-outlet (staf internal) 🟢
**Prioritas:** P0 · **Estimasi:** 5 · **Blok:** OPS-801
Scoping data per outlet untuk staf internal (Area Manager hanya outlet binaannya). **Investor tidak login app** (terima laporan via WA saja) — di luar scope auth.
**Acceptance criteria:**
- [ ] Assignment user↔outlet; semua query (laporan, sinyal, revenue) ter-scope per outlet.
- [ ] Tidak ada kebocoran data outlet lintas Area Manager.

### OPS-1004 · Versioning template (draft → preview → publish) 🟢
**Prioritas:** P1 · **Estimasi:** 5 · **Blok:** OPS-901, OPS-902
Edit master template tidak boleh live-merusak semua outlet yang mewarisi.
**Acceptance criteria:**
- [ ] Perubahan template lewat draft → preview → publish (berversi); bisa rollback.
- [ ] Publish master menampilkan dampak ke outlet yang mewarisi sebelum berlaku.

### OPS-1005 · Investor master ringan + definisi periode/cutoff 🟢
**Prioritas:** P1 · **Estimasi:** 3 · **Blok:** OPS-101, OPS-201
Investor sebagai entitas ringan (1:1 outlet): nama, kontak WA, outlet, sejak kapan — memudahkan re-invite (OPS-307) & link CRM. Tetapkan **periode laporan = hari kalender penuh, dikirim setelah hari ditutup** (atau cutoff eksplisit).
**Acceptance criteria:**
- [ ] Tabel investor ringan; tertaut ke `delivery_targets`.
- [ ] Periode & cutoff laporan terdefinisi & konsisten dengan Penyesuaian Revenue.

---

## Dependensi Non-Teknis (di luar kendali engineering)

- **OBA (Official Business Account):** prasyarat OPS-303/304 (Opsi A). Nomor baru sedang dibeli (per 12 Juni 2026) → lanjutkan ke verifikasi OBA. Catatan: rencana mengirim ke grup WA via API = jalur Opsi A, jadi tetap menunggu OBA.
- **Kebijakan self-approval — RESOLVED:** wewenang ganda request+approve diizinkan untuk level >= Kepala Toko. OPS-601 mengkodekan aturan ini -> butuh referensi hierarki role (peta `id_role` -> level; konfirmasi `id_role` mana yang setara/di atas Kepala Toko).
- **Field "nota pengganti" di NEVIRA:** akan disediakan tapi tidak dalam waktu dekat -> OPS-604 pakai heuristik dulu sebagai mode utama.
- **SOP kasir:** penegakan pencantuman nota pengganti & disiplin input — memengaruhi kualitas sinyal OPS-603/604.
- **SSO/identitas pusat ERP/LBE:** OMS pakai auth sendiri dulu; bila ERP menyediakan SSO, `IdentityProvider` (OPS-801) di-connect ke LBE. Keputusan "di mana identitas pusat tinggal" ada di level ERP, bukan Modul 1.
- **Approved Meta template fleksibel:** untuk transport Opsi A (OPS-903), body 1 parameter besar perlu diajukan & disetujui Meta — paralel OBA.
- **Kredensial NEVIRA:** di `.env`/secret store, BUKAN fitur CRUD (satu kredensial stabil). Akun WhatsApp simpan referensi secret, bukan mentah.
- **KWL (self-service):** beda pola dari LW → di luar scope Modul 1; rancang sebagai profil/adapter terpisah. Sinyal input-error & self-approval mungkin tak relevan tanpa kasir.

---

## Urutan Sprint yang Disarankan

1. **Sprint 1 (Fondasi + Auth):** OPS-101, 102, 103, 104, 105, 108, 705, 701, 801 → pipeline data + token lifecycle NEVIRA + auth app + skema/interface/PII siap.
2. **Sprint 2 (Admin inti + Template + Scoping):** OPS-802, 803, 804, 1003, 901, 903, 1005 → kelola user/outlet/target + scoping per-outlet + template + investor ringan. Template seed default agar pipeline bisa jalan.
3. **Sprint 3 (Laporan MVP hybrid):** OPS-201, 202, 203, 204, 206, 301, 302, 305, 702, 704, 1001 → laporan harian (+konfirmasi-kirim hybrid, empty-state), dengan watchdog.
4. **Sprint 4 (Revenue + Outlet Diam):** OPS-401, 402, 403, 501, 502, 106 → koreksi & alert diam (jam cek dari konfigurasi, baseline anti-bias).
5. **Sprint 5 (Anomali, Integritas, Builder):** OPS-601, 602, 603, 604, 605, 606, 1002, 503, 404, 703, 805, 1004, **902 (drag & drop)**.
6. **Sprint 6 (Opsi A penuh + DR):** OPS-303, 304, 306, 307, 706 → grup-API + assisted → full_auto via cutover & canary + DR nomor + cost guard (setelah OBA + migrasi). OPS-107 (webhook) opsional.

> Rilis nilai paling cepat ada di akhir Sprint 3 (laporan otomatis hybrid). Builder drag & drop (OPS-902) di Sprint 5 karena pipeline sudah jalan dengan template seed/override — builder mempercantik, bukan memblokir. Opsi A penuh terakhir agar tidak memblokir.
