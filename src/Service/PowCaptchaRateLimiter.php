<?php

namespace PrestaShop\Module\PowCaptcha\Service;

class PowCaptchaRateLimiter
{
    private $maxRequests = 10;

    private $windowSeconds = 60;

    /** @var PowCaptchaFileCache */
    private $cache;

    public function __construct()
    {
        $directory = sys_get_temp_dir() . '/pow_captcha_for_prestashop-' . md5(__DIR__);

        $this->cache = new PowCaptchaFileCache([
            'cache_dir' => $directory,
        ]);
    }

    public function isAllowed(string $ip): bool
    {
        $requests = $this->getRecentRequests($ip);

        return count($requests) < $this->maxRequests;
    }

    public function recordRequest(string $ip): void
    {
        $requests = $this->getRecentRequests($ip);
        $requests[] = time();
        $this->cache->save($this->getCacheId($ip), $requests, $this->windowSeconds);
    }

    private function getRecentRequests(string $ip): array
    {
        $requests = $this->cache->get($this->getCacheId($ip));

        if (!is_array($requests)) {
            return [];
        }

        $cutoff = time() - $this->windowSeconds;

        return array_values(array_filter($requests, function ($timestamp) use ($cutoff) {
            return (int) $timestamp >= $cutoff;
        }));
    }

    private function getCacheId(string $ip): string
    {
        return 'rate_limit_' . hash('sha256', $ip);
    }
}
