<?php

namespace App\Integrations;

use App\Models\Integration;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Meta Graph API client for WhatsApp Cloud API and Lead Ads calls.
 */
final class MetaGraph
{
    public function sendText(Integration $whatsapp, string $to, string $body): string
    {
        return $this->sendMessage($whatsapp, $to, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    /**
     * @param  list<string>  $parameters  values for {{1}}, {{2}}, ... in the template body
     */
    public function sendTemplate(Integration $whatsapp, string $to, string $name, string $language, array $parameters): string
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];

        if ($parameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $parameters),
            ]];
        }

        return $this->sendMessage($whatsapp, $to, ['type' => 'template', 'template' => $template]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(Integration $whatsapp): array
    {
        $templates = [];
        $url = "/{$whatsapp->setting('waba_id')}/message_templates";
        $query = ['fields' => 'name,language,status,category,components', 'limit' => 100];

        do {
            $page = $this->client($whatsapp->setting('access_token'))->get($url, $query)->throw()->json();
            array_push($templates, ...($page['data'] ?? []));
            $url = $page['paging']['next'] ?? null;
            $query = [];
        } while ($url !== null);

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    public function lead(Integration $facebook, string $leadgenId): array
    {
        return $this->client($facebook->setting('page_access_token'))
            ->get("/{$leadgenId}")
            ->throw()
            ->json();
    }

    /**
     * Who the saved WhatsApp credentials connect to.
     *
     * @return array{display_phone_number?: string, verified_name?: string, quality_rating?: string}
     */
    public function whatsappNumber(Integration $whatsapp): array
    {
        return $this->client($whatsapp->setting('access_token'))
            ->get("/{$whatsapp->setting('phone_number_id')}", ['fields' => 'display_phone_number,verified_name,quality_rating'])
            ->throw()
            ->json();
    }

    /**
     * The Facebook page the saved page token belongs to.
     *
     * @return array{id?: string, name?: string}
     */
    public function facebookPage(Integration $facebook): array
    {
        return $this->client($facebook->setting('page_access_token'))
            ->get('/me', ['fields' => 'id,name'])
            ->throw()
            ->json();
    }

    /**
     * Where to download a file a lead sent. The link works for a few minutes.
     *
     * @return array{url?: string, mime_type?: string, file_size?: int}
     */
    public function media(Integration $whatsapp, string $mediaId): array
    {
        return $this->client($whatsapp->setting('access_token'))
            ->get('/'.rawurlencode($mediaId))
            ->throw()
            ->json();
    }

    /**
     * The file itself. The access token is only ever sent to Meta's own
     * file servers.
     */
    public function download(Integration $whatsapp, string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! preg_match('/(^|\.)(fbsbx\.com|facebook\.com|whatsapp\.net)$/', $host)) {
            throw new DomainException("Refusing to download WhatsApp media from {$host}.");
        }

        return Http::withToken((string) $whatsapp->setting('access_token'))
            ->timeout(60)
            ->withoutRedirecting()
            ->get($url)
            ->throw()
            ->body();
    }

    /**
     * Meta signs webhook bodies with the app secret (X-Hub-Signature-256).
     */
    public static function hasValidSignature(string $payload, string $header, ?string $appSecret): bool
    {
        return filled($appSecret)
            && hash_equals('sha256='.hash_hmac('sha256', $payload, $appSecret), $header);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function sendMessage(Integration $whatsapp, string $to, array $message): string
    {
        return (string) $this->client($whatsapp->setting('access_token'))
            ->post("/{$whatsapp->setting('phone_number_id')}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                ...$message,
            ])
            ->throw()
            ->json('messages.0.id');
    }

    private function client(?string $token): PendingRequest
    {
        $base = rtrim(config('services.meta.graph_url'), '/').'/'.config('services.meta.graph_version');

        return Http::baseUrl($base)->withToken((string) $token)->acceptJson()->asJson()->timeout(20);
    }
}
