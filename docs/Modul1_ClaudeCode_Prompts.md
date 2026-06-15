# Prompt Pack — Eksekusi Modul 1 via Claude Code

Aplikasi: **Operations Management System (OMS)**. Pack ini fokus **Sprint 1 (fondasi backend)**. Sprint 2+ (Admin, template, dll.) ada di `Modul1_Engineering_Tickets.md`.

Cara pakai: tempel **satu prompt per sesi**. Jalankan Prompt 0 lebih dulu (orientasi, belum ngoding). Setelah rencana disetujui, kerjakan tiket berurutan: OPS-101 → 102 → 108 → 103 → 104 → 705 (lalu 701, 801 sesuai Sprint 1). Tiap tiket = satu branch + review sebelum lanjut.

Asumsi: `CLAUDE.md` ada di root repo; dokumen perencanaan ada di `docs/` (sesuaikan bila berbeda).

---

## Prompt 0 — Orientasi (JANGAN ngoding dulu)

```
Kamu akan mengerjakan Modul 1 aplikasi Operations Management System (OMS) — Apique Group.

SEBELUM menulis kode apa pun:
1. Baca CLAUDE.md di root, lalu docs/Modul1_System_Design.md, docs/Modul1_Engineering_Tickets.md, dan docs/Modul1_Architecture_Review.md.
2. Ringkas dalam bahasa Indonesia: (a) gaya arsitektur & modul domain, (b) aturan emas, (c) 9 risiko di architecture review.
3. Sajikan rencana Sprint 1 dalam urutan OPS-101 → 102 → 108 → 103 → 104 → 705 (lalu 701, 801). Untuk tiap tiket sebutkan file utama yang akan kamu buat dan test yang akan ditulis.
4. JANGAN menulis kode atau migrasi sekarang. Tunggu persetujuanku atas rencana.

Jika ada yang ambigu atau bertentangan dengan CLAUDE.md, berhenti dan tanyakan — jangan berasumsi.
```

---

## Prompt 1 — OPS-101 (skema data output, LBE-ready)

```
Kerjakan tiket OPS-101. Baca acceptance criteria-nya di docs/Modul1_Engineering_Tickets.md dan tabel skema di docs/Modul1_System_Design.md §3.3.

Buat migrasi Laravel untuk tabel: outlets, outlet_baselines, report_runs, report_deliveries, revenue_adjustments, signal_events, cashier_input_scores.

Aturan wajib (CLAUDE.md):
- Hanya OUTPUT TURUNAN. Simpan referensi transaction_number/id_transaction, JANGAN menduplikasi data transaksi NEVIRA.
- JANGAN ada kolom PII customer (nama, telepon, alamat) di mana pun.
- Semua tabel ber-id_outlet + berstempel waktu (LBE-ready).
- Indeks: report_runs(id_outlet, report_date), signal_events(id_outlet, type, detected_at), revenue_adjustments(restated_for_date).
- Migrasi harus reversible (down() benar).

Tulis test (Pest) yang memverifikasi migrate + rollback berjalan dan indeks ada.

Selesai: tampilkan daftar file yang dibuat, centang tiap acceptance criteria OPS-101, buat branch feature/OPS-101 dan commit lokal (jangan push). Berhenti & tanyakan bila ada ambiguitas.
```

---

## Prompt 2 — OPS-102 (klien NEVIRA di balik interface)

```
Kerjakan tiket OPS-102. Acuan: docs/Modul1_System_Design.md §3.2 dan §3.1.

INTERFACE DULU, implementasi kemudian:
1. Definisikan interface TransactionSource dengan method: dailyDashboard(outletId, date), voidRefunds(outletId, dateRange), unpaid(outletId, dateRange).
2. Implementasi NeviraApiSource memakai Laravel Http: auth Bearer token dari config/secret (JANGAN hardcode), retry + backoff, hormati HTTP 429, paginasi otomatis (next_page_url/last_page → kumpulkan semua halaman).

Aturan wajib:
- Domain lain HARUS bergantung pada interface TransactionSource, bukan klien HTTP konkret (anti-corruption layer).
- Akses NEVIRA hanya via REST API — tidak ada direct DB.

Test: contract test memakai fixture response NYATA. Ambil contoh JSON void/refund/unpaid dari docs (atau aku tempelkan), simpan sebagai fixture, dan mock HTTP. Pastikan paginasi & penanganan 429 teruji.

Selesai: daftar file, centang acceptance criteria OPS-102, branch feature/OPS-102, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Prompt 2b — OPS-108 (token lifecycle & re-auth NEVIRA) 🔑

```
Kerjakan tiket OPS-108. Acuan: docs/Modul1_System_Design.md §3.2 dan CLAUDE.md bagian "Auth NEVIRA".

Fakta: NEVIRA pakai LOGIN TOKEN berlaku 24 jam. Bangun token manager di dalam NeviraApiSource:
- Re-auth = panggil login endpoint dengan SERVICE CREDENTIAL (di secret store, BUKAN cuma token).
- Token di-cache BERSAMA (Redis) + waktu perolehan; dipakai semua worker.
- Proaktif: refresh bila umur token mendekati 24 jam (mis. >= 23 jam) atau saat awal run.
- Reaktif pada 401: SINGLE-FLIGHT re-login (satu proses; request lain menunggu) lalu retry request asli SEKALI.
- Bila re-auth gagal: alert jelas + fallback, BERHENTI (tidak loop).
- 401/403 BUKAN error transient — jangan di-backoff seperti 429/5xx.

Test (Pest):
(a) token kedaluwarsa -> re-login otomatis, request lanjut;
(b) banyak worker bersamaan -> re-login terjadi SEKALI (single-flight/lock);
(c) re-auth gagal -> alert + fallback, bukan retry tak hingga;
(d) tidak ada kredensial/secret di log.

Selesai: daftar file, centang acceptance criteria OPS-108, branch feature/OPS-108, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Prompt 3 — OPS-103 (parser + normalisasi waktu) ⚠️ risiko timezone

```
Kerjakan tiket OPS-103. Acuan pemetaan field: CLAUDE.md bagian "NEVIRA — integrasi" dan docs/Modul1_System_Design.md §3.3.

Buat DTO/model internal dari response transaksi NEVIRA (transaction_number, status, grand_total, created_at, approve_refund_void_date, refund_notes/void_notes, refund_void_by, refund_void_approved_by, payment_status, id_cashier, progress_percentage).

KRITIS (aturan emas #4 + Risiko R1 di architecture review):
- Timestamp tingkat-transaksi (created_at, approve_refund_void_date) berformat WIB.
- Timestamp nested di "services" berformat UTC (beda 7 jam).
- Untuk SEMUA logika tanggal, pakai field tingkat-transaksi dan NORMALKAN eksplisit ke Asia/Jakarta. Jangan campur dengan timestamp nested.
- Parser harus tahan field null (mis. void_notes null saat record REFUND).

Test WAJIB (Pest):
(a) parsing fixture void & refund nyata;
(b) test batas tengah malam: transaksi pukul 23:30 WIB tidak boleh tergeser ke tanggal lain karena salah interpretasi zona.

Selesai: daftar file, centang acceptance criteria OPS-103, branch feature/OPS-103, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Prompt 4 — OPS-104 (scheduler + idempotency)

```
Kerjakan tiket OPS-104. Acuan: docs/Modul1_System_Design.md §3.5.

Bangun:
- Penjadwalan via Laravel Scheduler: dispatch job per outlet pada jam masing-masing, timezone Asia/Jakarta.
- Job async lewat queue (driver Redis bila ada, fallback database).
- Job dasar GenerateDailyReportJob (boleh stub untuk sekarang) + middleware WithoutOverlapping per outlet.

Aturan wajib (aturan emas #5):
- Idempotency: kunci per (outlet, report_date) dan (report_run, channel). Re-run/replay TIDAK boleh menghasilkan efek/kiriman ganda — "tepat satu channel aktif per target per hari".
- Kegagalan job tercatat & dapat di-retry.

Test (Pest): dispatch job dua kali untuk (outlet, tanggal) yang sama → hanya satu efek; verifikasi penjadwalan per-outlet menghormati timezone.

Selesai: daftar file, centang acceptance criteria OPS-104, branch feature/OPS-104, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Prompt 5 — OPS-705 (kebijakan minim-PII & retensi)

```
Kerjakan tiket OPS-705. Acuan: docs/Modul1_System_Design.md §3.6 dan aturan emas #3.

Pastikan:
- Tidak ada kolom atau serialisasi yang mempersist PII customer (nama, telepon, alamat) dari record void/refund di tabel output mana pun. Yang boleh disimpan hanya metadata sinyal: transaction_number, nominal, alasan, id_cashier, tanggal.
- Kebijakan retensi terimplementasi: command/job terjadwal yang membersihkan payload mentah/cache fetch setelah N hari (N dapat dikonfigurasi).

Test (Pest): test yang GAGAL bila ada field PII customer muncul di tabel output; test bahwa job retensi membersihkan data lewat ambang umur.

Selesai: daftar file, centang acceptance criteria OPS-705, ringkasan singkat review privasi, branch feature/OPS-705, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Prompt UI — Implementasi desain (Claude Design) 🎨

> **Kapan dipakai:** saat MULAI kerja frontend (Sprint 2+), BUKAN di Sprint 1 backend. Layar butuh model/endpoint dari Sprint 1 lebih dulu. Jalankan per-layar, bukan sekaligus.

```
Fetch file desain ini, baca README-nya lebih dulu, lalu implementasikan aspek desain yang relevan dengan project ini:
https://api.anthropic.com/v1/design/h/2MwwnhYwW2jW7ZJf_bML6Q

Langkah:
1. Fetch & baca README/overview desain dulu. Ringkas dalam bahasa Indonesia: ada layar/komponen apa saja, dan design system-nya (warna, tipografi, spacing).
2. PETAKAN tiap layar desain ke tiket OMS yang relevan sebelum ngoding, mis.:
   - Dashboard/laporan mobile  -> OPS-204 (render gambar dashboard) / OPS-206 (compose laporan)
   - Halaman Admin (user/outlet/akun & target WhatsApp) -> OPS-802 / OPS-803 / OPS-804
   - Builder template drag & drop -> OPS-902
   - Halaman tinjauan sinyal -> OPS-606
   (Sesuaikan dengan layar yang benar-benar ada di desain.)
3. Implementasikan SATU layar per sesi, mengikuti tiket pemetaannya — jangan bangun semua sekaligus.
4. Patuhi CLAUDE.md (modul domain, no PII, scoping per-outlet) DAN design system dari file desain. Frontend pakai stack yang ditetapkan (Filament/Livewire/Blade).
5. Bila sebuah layar butuh data/endpoint yang BELUM dibangun di Sprint 1/2, BERHENTI dan beri tahu aku tiket backend mana yang harus didahulukan — jangan buat data palsu permanen.

Selesai per layar: daftar file, centang acceptance criteria tiket terkait, branch feature/OPS-xxx, commit lokal. Berhenti & tanyakan bila ambigu.
```

---

## Bonus — Buat project skill untuk mempercepat sprint berikutnya

```
Buat sebuah project skill di .claude/skills/scaffold-job/SKILL.md.

Tujuan: ketika dipanggil (/scaffold-job), Claude men-scaffold sebuah Laravel Queue Job baru sesuai konvensi modul kita:
- Tempatkan di namespace domain yang sesuai (Ingestion/Reporting/Revenue/Signals/Delivery).
- implements ShouldQueue; sertakan idempotency key & WithoutOverlapping bila relevan.
- Struktur handle() yang bersih; injeksi TransactionSource lewat constructor bila butuh data NEVIRA (jangan klien konkret).
- Hasilkan file test Pest pendamping (happy path + satu edge case).
- Patuhi seluruh aturan emas di CLAUDE.md (no PII, waktu WIB, no direct DB, idempotency).

Setelah skill dibuat, tunjukkan contoh pemakaian singkat. Jangan jalankan scaffold sekarang — cukup buat skill-nya.
```

---

## Pengingat alur kerja

- Satu tiket = satu sesi = satu branch `feature/OPS-xxx` = satu PR. Mudah di-review.
- Tiap selesai, minta Claude mencentang acceptance criteria & Definition of Done (CLAUDE.md).
- Bila Claude mengusulkan keputusan arsitektur baru, suruh berhenti & konfirmasi ke kamu dulu.
- Jalankan `php artisan test` sebelum menutup tiap tiket.
