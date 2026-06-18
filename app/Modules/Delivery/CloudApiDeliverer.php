<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\Contracts\Deliverer;
use App\Modules\Delivery\Exceptions\DeliveryFailed;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Mode assisted/full_auto via WhatsApp Cloud API (Groups) — System Design §3.8 / OPS-303.
 *
 * Implementasi penuh + teruji sandbox. Master switch `whatsapp.enabled` default OFF → menolak
 * (DeliveryFailed) sehingga DeliveryDispatcher fallback ke hybrid. Go-live OBA = nyalakan config +
 * isi kredensial; TIDAK perlu ubah kode. Tak ada kiriman diam-diam: setiap precondition gagal →
 * DeliveryFailed (dispatcher fallback + alert). Idempoten per (report_run, channel).
 */
final class CloudApiDeliverer implements Deliverer
{
    public const CHANNEL = 'cloud_api';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly WhatsappCredentials $credentials,
        private readonly ApprovedTemplateFit $fit,
    ) {}

    public function mode(): string
    {
        return 'assisted'; // menangani assisted & full_auto
    }

    public function deliver(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        // Idempotency: jika sudah terkirim via Cloud API utk run ini → jangan kirim ulang.
        $existing = ReportDelivery::where('report_run_id', $run->id)->where('channel', self::CHANNEL)->first();
        if ($existing && $existing->isConfirmedDelivered()) {
            return $existing;
        }

        $this->assertReady($run, $target);

        $account = $target->whatsappAccount;
        $token = $this->credentials->resolve($account);
        $text = (string) $run->payload_text;

        $response = $this->http
            ->baseUrl((string) config('whatsapp.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->timeout((int) config('whatsapp.timeout', 20))
            ->post($this->messagesPath(), $this->templatePayload($target->group_id, $text));

        if ($response->failed()) {
            // Bukan kegagalan diam-diam: lempar → dispatcher fallback hybrid + alert. Jangan log token.
            throw new DeliveryFailed(
                "Cloud API kirim gagal (HTTP {$response->status()}) outlet {$run->id_outlet}."
            );
        }

        $messageId = (string) ($response->json('messages.0.id') ?? '');
        Log::channel('oms')->info('delivery.cloud_api_sent', [
            'report_run_id' => $run->id, 'id_outlet' => $run->id_outlet,
            'group_id' => $target->group_id, 'message_id' => $messageId,
        ]);

        return ReportDelivery::updateOrCreate(
            ['report_run_id' => $run->id, 'channel' => self::CHANNEL],
            [
                'id_outlet' => $run->id_outlet,
                'target' => $target->investor_label,    // label, bukan PII
                'status' => ReportDelivery::SENT,
                'sent_at' => now(),
                'error' => null,
                'idempotency_key' => IdempotencyKey::delivery($run->id, self::CHANNEL),
            ],
        );
    }

    /** Precondition kirim. Tiap gagal → DeliveryFailed (fallback hybrid). */
    private function assertReady(ReportRun $run, DeliveryTarget $target): void
    {
        if (! (bool) config('whatsapp.enabled', false)) {
            throw new DeliveryFailed('Cloud API dimatikan (whatsapp.enabled=false) — menunggu OBA (OPS-303).');
        }
        if (! config('whatsapp.phone_number_id')) {
            throw new DeliveryFailed('phone_number_id Cloud API belum dikonfigurasi.');
        }

        $account = $target->whatsappAccount;
        if ($account === null || ! $account->obaReady()) {
            throw new DeliveryFailed('Akun WA OBA tak siap (OPS-306) — fallback hybrid.');
        }
        if (empty($target->group_id)) {
            throw new DeliveryFailed('group_id target kosong — grup belum siap.');
        }
        if ($this->credentials->resolve($account) === null) {
            throw new DeliveryFailed('Kredensial Cloud API tak ter-resolve dari credentials_ref.');
        }
        if (! $this->fit->fits((string) $run->payload_text)) {
            throw new DeliveryFailed('Konten tak muat approved template (R7) — fallback hybrid paste manual.');
        }
    }

    private function messagesPath(): string
    {
        return '/'.config('whatsapp.api_version').'/'.config('whatsapp.phone_number_id').'/messages';
    }

    /** Payload approved template: 1 parameter body besar (konten render). */
    private function templatePayload(string $groupId, string $text): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $groupId,
            'type' => 'template',
            'template' => [
                'name' => (string) config('whatsapp.template.name'),
                'language' => ['code' => (string) config('whatsapp.template.language', 'id')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [['type' => 'text', 'text' => $text]],
                ]],
            ],
        ];
    }
}
