<?php
namespace App\Services;

/**
 * Whether the web server is applying Whisker's access rules.
 *
 * The shipped .htaccess keeps code, configuration and the database schema
 * off the web, but nginx never reads it and Apache ignores it under
 * AllowOverride None. The only way to know is to ask the server for a
 * file it should refuse and see what comes back.
 *
 * run() and message() have no dependencies, because the installer loads
 * this file before the autoloader exists.
 */
final class ExposureCheck
{
    /** Files every install has, which a browser must never be handed. */
    public const PROBES = [
        'app/version.php',
        'config/config.sample.php',
        'sql/schema.sql',
        '.htaccess',
    ];

    private const CONNECT_TIMEOUT = 3;
    private const TIMEOUT = 5;

    /** How long a dashboard result stands before the server is asked again. */
    private const RECHECK_AFTER = 7 * 86400;

    /**
     * The warning for the admin dashboard, if any. The probes run at most
     * once a week; the result lives in wk_settings with the other caches.
     */
    public static function cachedMessage(string $baseUrl): ?string
    {
        $cached = \Core\Database::fetchValue(
            "SELECT setting_value FROM wk_settings WHERE setting_group='system_cache' AND setting_key='exposure_check'"
        );
        $data = $cached ? json_decode((string) $cached, true) : null;
        if (!is_array($data) || ($data['checked_at'] ?? 0) < time() - self::RECHECK_AFTER) {
            $data = self::run($baseUrl) + ['checked_at' => time()];
            \Core\Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system_cache', 'exposure_check', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [json_encode($data)]
            );
        }
        return self::message($data);
    }

    /**
     * Ask the server for each probe and report the ones it handed over.
     *
     * A 200 is the only finding. A refusal, a missing file or a redirect
     * all mean the file is not being served as-is. If the first probe gets
     * no answer at all, the rest are skipped: the site is not reachable
     * from its own server, which some hosts block, and that is a hosting
     * quirk rather than a finding.
     *
     * @return array{exposed: string[], reached: bool}
     */
    public static function run(string $baseUrl): array
    {
        $exposed = [];
        $reached = false;

        if (PHP_SAPI === 'cli-server' || !function_exists('curl_init')) {
            return ['exposed' => [], 'reached' => false];
        }

        foreach (self::PROBES as $path) {
            $status = self::status(rtrim($baseUrl, '/') . '/' . $path);
            if ($status === null) {
                if (!$reached) break;
                continue;
            }
            $reached = true;
            if ($status === 200) {
                $exposed[] = $path;
            }
        }

        return ['exposed' => $exposed, 'reached' => $reached];
    }

    /** One sentence a shopkeeper can act on, or null when there is nothing to say. */
    public static function message(array $result): ?string
    {
        if (empty($result['exposed'])) {
            return null;
        }
        return 'Your web server is handing out files it should refuse ('
            . implode(', ', $result['exposed'])
            . '). On nginx, add the access rules from the install guide. '
            . 'On Apache, make sure AllowOverride is on for the Whisker folder so .htaccess applies.';
    }

    private static function status(string $url): ?int
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Whisker exposure check',
        ]);
        curl_exec($ch);
        $error  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ($error !== 0 || $status === 0) ? null : $status;
    }
}
