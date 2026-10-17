<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\Contracts\GoogleHttp;
use Yinghua\DTO\RemoteResponse;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\GoogleUrlPolicy;

/** [E-19] 不自動 redirect；每次驗 DNS 並釘選 IP，避免驗證與連線之間 DNS 改變。 */
class CurlGoogleHttp implements GoogleHttp
{
    public function __construct(private readonly GoogleUrlPolicy $policy) {}

    /** @param array<string,string> $headers */
    public function request(string $url, array $headers = [], ?string $body = null, bool $api = false): RemoteResponse
    {
        $host = $this->policy->validate($url, $api);
        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw new ApplicationError('MAP_UNAVAILABLE', '地圖服務暫時無法連線，可改為人工輸入。');
        }
        foreach ($ips as $ip) {
            if (!$this->policy->publicIp($ip)) {
                throw ApplicationError::invalid('mapUrl', '地圖網址解析到不允許的網路位置。');
            }
        }
        $curl = curl_init($url);
        $received = '';
        $responseHeaders = [];
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => false,CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 1,CURLOPT_TIMEOUT => 2,CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_PROXY => '',CURLOPT_SSL_VERIFYPEER => true,CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$host . ':443:' . implode(',', $ips)],
            CURLOPT_USERAGENT => 'SPL-Content-Importer/1.0',
            CURLOPT_HTTPHEADER => array_map(static fn(string $key, string $value): string => $key . ': ' . $value, array_keys($headers), array_values($headers)),
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $handle, string $chunk) use (&$received): int {
                if (strlen($received) + strlen($chunk) > 262144) {
                    return 0;
                }
                $received .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $handle, string $line) use (&$responseHeaders): int {
                $colon = strpos($line, ':');
                if ($colon !== false) {
                    $responseHeaders[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
                }

                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $success = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($success === false) {
            throw new ApplicationError('MAP_UNAVAILABLE', '地圖服務逾時或回應過大，可改為人工輸入。');
        }

        return new RemoteResponse($status, $received, $responseHeaders);
    }
}
