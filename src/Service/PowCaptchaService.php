<?php

namespace PrestaShop\Module\PowCaptcha\Service;

use Configuration;
use PrestaShopLoggerCore;
use Tools;

class PowCaptchaService
{
    protected $client;

    protected $timeout = 5.0;

    protected $cacheLifetime = 3600;

    public function __construct()
    {
        if (defined('POW_CAPTCHA_TIMEOUT')) {
            $this->timeout = (float) POW_CAPTCHA_TIMEOUT;
        }

        if (defined('POW_CAPTCHA_CACHE_LIFETIME')) {
            $this->cacheLifetime = (int) POW_CAPTCHA_CACHE_LIFETIME;
        }
    }

    /**
     * Retrieves the cURL handle for making API requests.
     *
     * @return The cURL handle.
     */
    public function getClient()
    {
        if (!$this->client) {
            $apiToken = Configuration::get('POW_CAPTCHA_API_TOKEN');

            $this->client = curl_init();
            curl_setopt($this->client, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($this->client, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiToken,
            ]);
            curl_setopt($this->client, CURLOPT_RETURNTRANSFER, true);
        }

        return $this->client;
    }

    /**
     * Loads the challenges from the API.
     *
     * @return array|null The challenges data as an associative array.
     */
    public function loadChallenges()
    {
        $domain = Configuration::get('POW_CAPTCHA_API_URL');
        $url = 'GetChallenges?difficultyLevel=5';
        $requestUri = $domain . $url;

        $ch = $this->getClient();

        curl_setopt($ch, CURLOPT_URL, $requestUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);

        $json = curl_exec($ch);
        $data = json_decode($json, true);

        return $data;
    }

    /**
     * Retrieves a challenge from the cache or loads it from the API.
     *
     * @return string|null The challenge string.
     */
    public function getChallenge()
    {
        // Generate a directory path to store the cache
        $directory = sys_get_temp_dir() . '/pow_captcha_for_prestashop-' . md5(__DIR__);

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Define lock file path
        $lockFile = $directory . '/lock.file';
        $lockHandle = null;

        try {
            // Acquire lock
            $lockHandle = fopen($lockFile, 'c+');
            if (!$lockHandle) {
                return null;
            }

            // Try to get an exclusive lock, wait for a maximum of 5 seconds
            $waitTime = 0;
            $maxWait = $this->timeout;
            $waitStep = 0.1; // 100ms per step

            while (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                // Sleep for a short time
                usleep($waitStep * 1000000);
                $waitTime += $waitStep;

                if ($waitTime >= $maxWait) {
                    // Could not acquire lock after waiting, return a fallback
                    fclose($lockHandle);
                    return null;
                }
            }

            // Initialize cache
            $cache = new PowCaptchaFileCache([
                'cache_dir' => $directory,
            ]);

            // Define the cache key for storing the challenges
            $cacheKey = 'pow_captcha_for_prestashop_challenges';
            $lifetime = $this->cacheLifetime;

            // Retrieve the challenges from the cache using the cache key
            $challenges = $cache->get($cacheKey);

            if (!$challenges) {
                // If the challenges are not found in the cache, load them from the API
                $challenges = $this->loadChallenges();
            } else {
                if (count($challenges) < 5) {
                    // If the number of challenges is less than 5, reload them from the API
                    $challenges = $this->loadChallenges();
                }
            }

            // Get the first challenge from the array
            $challenge = array_shift($challenges);

            // Save the challenges cache
            $cache->save($cacheKey, $challenges, $lifetime);

            // Return the retrieved challenge
            return $challenge;
        } finally {
            // Release lock if we have a valid handle
            if ($lockHandle) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    /**
     * Validates the CAPTCHA challenge and nonce.
     *
     * @param string $challenge The CAPTCHA challenge.
     * @param string $nonce The nonce value.
     *
     * @return bool Indicates whether the CAPTCHA is valid or not.
     */
    public function validateCaptcha($challenge, $nonce): bool
    {
        $domain = Configuration::get('POW_CAPTCHA_API_URL');

        if (empty($challenge) || empty($nonce)) {
            $challengeDisplay = is_null($challenge) ? 'undefined' : $challenge;
            $nonceDisplay = is_null($nonce) ? 'undefined' : $nonce;

            PrestaShopLoggerCore::addLog(
                "[pow_captcha] Challenge or nonce is not defined: challenge=[{$challengeDisplay}] nonce=[{$nonceDisplay}]",
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR
            );
            return false;
        }

        $url = sprintf('Verify?challenge=%s&nonce=%s', $challenge, $nonce);
        $requestUri = $domain . $url;
        $ip = Tools::getRemoteAddr();

        $ch = $this->getClient();
        curl_setopt($ch, CURLOPT_URL, $requestUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        try {
            $result = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                PrestaShopLoggerCore::addLog("[pow_captcha] ip=[{$ip}] | cURL error during captcha validation: error=[{$error}]", PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR);
                return false;
            }

            if ($statusCode !== 200) {
                PrestaShopLoggerCore::addLog("[pow_captcha] ip=[{$ip}] | Invalid captcha response: status=[{$statusCode}] response={$result}", PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR);
            }

            return $statusCode === 200;
        } catch (\Exception $e) {
            PrestaShopLoggerCore::addLog("[pow_captcha] ip=[{$ip}] | Exception during captcha validation: " . $e->getMessage(), PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR);
            return false;
        }
    }

    /**
     * Closes the cURL handle when the object is destroyed.
     */
    public function __destruct()
    {
        if ($this->client) {
            curl_close($this->client);
        }
    }
}
