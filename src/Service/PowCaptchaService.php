<?php

namespace PrestaShop\Module\PowCaptcha\Service;

use Configuration;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class PowCaptchaService
{
    protected $client;

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
            curl_setopt($this->client, CURLOPT_TIMEOUT, 5.0);
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

        // Define the cache key for storing the challenges
        $cache_key = 'pow_captcha_for_prestashop_challenges';

        // Generate a directory path to store the cache
        $directory = sys_get_temp_dir() . '/pow_captcha_for_prestashop-' . md5(__DIR__);

        // Create a new filesystem cache adapter, caching the values for a month
        $cache = new FilesystemAdapter('pow_captcha_for_prestashop', 30 * 24 * 3600, $directory);

        // Retrieve the challenges from the cache using the cache key
        $challengesCache = $cache->getItem($cache_key);

        if (!$challengesCache->isHit()) {
            // If the challenges are not found in the cache, load them from the API
            $challenges = $this->loadChallenges();
        } else {
            // If the challenges are found in the cache, retrieve them
            $challenges = $challengesCache->get();

            if (count($challenges) < 5) {
                // If the number of challenges is less than 5, reload them from the API
                $challenges = $this->loadChallenges();
            }
        }

        // Get the first challenge from the array
        $challenge = array_shift($challenges);

        // Update the challenges cache with the remaining challenges
        $challengesCache->set($challenges);

        // Save the challenges cache
        $cache->save($challengesCache);

        // Return the retrieved challenge
        return $challenge;
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
        $url = sprintf('Verify?challenge=%s&nonce=%s', $challenge, $nonce);
        $requestUri = $domain . $url;

        $ch = $this->getClient();
        curl_setopt($ch, CURLOPT_URL, $requestUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);

        try {
            curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            return $statusCode === 200;
        } catch (\Exception $e) {
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
