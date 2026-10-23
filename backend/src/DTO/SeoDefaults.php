<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\SeoRobots;
use Yinghua\Policies\SeoUrlPolicy;

/** [S：E32；E-28] 上限為資源邊界，不是 Excel 建議字數的強制限制。 */
readonly class SeoDefaults
{
    public const array FIELDS = ['siteName','titleSuffix','description','keywords','canonicalOrigin','robots','ogImageUrl','twitterCard','twitterSite','googleVerification','bingVerification'];

    public function __construct(
        public string $siteName = '映畫竹塹',
        public string $titleSuffix = '｜映畫竹塹',
        public string $description = '從新竹市影像博物館出發，探索電影、美食、活動與城市旅行。',
        public string $keywords = '新竹旅遊,新竹景點,新竹美食,映畫竹塹',
        public string $canonicalOrigin = '',
        public SeoRobots $robots = SeoRobots::Index,
        public string $ogImageUrl = 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=1200&q=85',
        public string $twitterCard = 'summary_large_image',
        public string $twitterSite = '',
        public string $googleVerification = '',
        public string $bingVerification = '',
    ) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(self::FIELDS);
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                throw ApplicationError::invalid('defaults.' . $field, '請重新讀取完整設定後儲存。');
            }
        }
        $robots = SeoRobots::tryFrom($input->text('robots', 0, 40));
        $card = $input->text('twitterCard', 0, 40);
        if ($robots === null || !in_array($card, ['summary','summary_large_image'], true)) {
            throw ApplicationError::invalid($robots === null ? 'defaults.robots' : 'defaults.twitterCard', '請選有效的設定。');
        }
        $site = $input->text('twitterSite', 0, 40);
        if ($site !== '' && !preg_match('/^@[A-Za-z0-9_]{1,15}$/D', $site)) {
            throw ApplicationError::invalid('defaults.twitterSite', '請填 @帳號或留空。');
        }
        $token = static function (string $key) use ($input): string {
            $value = $input->text($key, 0, 255);
            if ($value !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
                throw ApplicationError::invalid('defaults.' . $key, '只填驗證 token，不貼 HTML、帳密或 API Key。');
            }
            return $value;
        };

        return new self(
            $input->text('siteName', 0, 160),
            $input->text('titleSuffix', 0, 160),
            $input->text('description', 0, 500),
            $input->text('keywords', 0, 1000),
            SeoUrlPolicy::https($input->text('canonicalOrigin', 0, 255), 'defaults.canonicalOrigin', true),
            $robots,
            SeoUrlPolicy::https($input->text('ogImageUrl', 0, 2000), 'defaults.ogImageUrl'),
            $card,
            $site,
            $token('googleVerification'),
            $token('bingVerification'),
        );
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['siteName' => $this->siteName,'titleSuffix' => $this->titleSuffix,'description' => $this->description,'keywords' => $this->keywords,
            'canonicalOrigin' => $this->canonicalOrigin,'robots' => $this->robots->value,'ogImageUrl' => $this->ogImageUrl,
            'twitterCard' => $this->twitterCard,'twitterSite' => $this->twitterSite,'googleVerification' => $this->googleVerification,'bingVerification' => $this->bingVerification];
    }
}
