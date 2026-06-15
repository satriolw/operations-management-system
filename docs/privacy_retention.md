# Kebijakan Privasi & Retensi — OPS-705

**Acuan:** System Design §3.6, CLAUDE.md aturan emas #2/#3/#7.

## Minim-PII (aturan emas #3)

Output DB **tidak menyimpan PII customer** (nama, telepon, alamat, email) dari record void/refund.
Yang boleh dipersist hanya **metadata sinyal**: `transaction_number`, nominal (`amount`/`grand_total`),
alasan (`reason`), `id_cashier`, dan tanggal.

- `app/Support/Privacy/PiiPolicy.php` — sumber kebenaran kebijakan:
  - `FORBIDDEN_COLUMN_TOKENS` — substring nama kolom yang dilarang di tabel output.
  - `ALLOWED_SIGNAL_FIELDS` — whitelist field payload sinyal.
  - `scrubSignalPayload()` — **wajib dipakai** modul Signals saat menyusun `payload_json`.
- Guard otomatis: `tests/Feature/Privacy/NoCustomerPiiColumnsTest.php` memindai SEMUA tabel output
  (dinamis) dan gagal bila ada kolom PII — termasuk tabel yang ditambah kemudian.

## Secret (aturan emas #7)

- Service credential NEVIRA (OPS-108) & token: di `.env`/secret store, **bukan** DB/repo.
- `whatsapp_accounts` menyimpan **referensi** (`credentials_ref`), bukan secret mentah.
- Tidak ada credential/token plaintext di log (diuji di OPS-108).

## Retensi data turunan

Payload **mentah** dibersihkan setelah N hari; angka turunan + referensi NEVIRA tetap (LBE-ready).

| Data | Aksi | Default | Env |
|---|---|---|---|
| `report_runs.payload_text`, `image_path` | dinolkan | 90 hari | `RETENTION_REPORT_PAYLOAD_DAYS` |
| `signal_events.payload_json` | dikosongkan | 180 hari | `RETENTION_SIGNAL_PAYLOAD_DAYS` |
| Cache fetch NEVIRA per-run | TTL pendek (Redis) | — | (bukan penyimpanan permanen) |

- Command: `php artisan oms:purge-raw-payloads` (idempoten).
- Jadwal: harian 03:00 WIB (`routes/console.php`).
- Implementasi: `app/Support/Privacy/RetentionPurger.php`.
