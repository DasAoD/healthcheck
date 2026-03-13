<?php
/**
 * includes/checker.php
 * Check execution: ICMP ping, HTTPS, TCP port
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Dispatches a check based on its type and returns the result.
 *
 * @param  array<string, mixed> $check  Row from the checks table
 * @return array{status: string, detail: string}
 */
function run_check(array $check): array
{
    $timeout = (int) setting('check_timeout_sec', '10');

    return match ($check['type']) {
        'ping'  => check_ping((string) $check['host'], $timeout),
        'https' => check_https((string) $check['host'], $timeout),
        'port'  => check_port((string) $check['host'], (int) $check['port'], $timeout),
        default => ['status' => 'unknown', 'detail' => 'Unknown check type: ' . $check['type']],
    };
}

// ── ICMP Ping ─────────────────────────────────────────────────────────────────

/**
 * Checks host reachability via ICMP ping (1 packet).
 *
 * @return array{status: string, detail: string}
 */
function check_ping(string $host, int $timeout = 10): array
{
    $host = escapeshellarg($host);

    // -c 1  = one packet, -W = wait timeout (seconds), -q = quiet output
    exec(
        sprintf('ping -c 1 -W %d %s 2>&1', $timeout, $host),
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        return ['status' => 'down', 'detail' => 'Host unreachable'];
    }

    // Parse latency from ping output, e.g. "rtt min/avg/max/mdev = 1.234/1.234/1.234/0.000 ms"
    $latency = parse_ping_latency(implode("\n", $output));

    $detail = $latency !== null
        ? sprintf('%.1f ms', $latency)
        : 'up';

    return ['status' => 'up', 'detail' => $detail];
}

/**
 * Extracts the average RTT in milliseconds from ping output.
 */
function parse_ping_latency(string $output): ?float
{
    if (preg_match('/rtt[^=]+=\s*[\d.]+\/([\d.]+)\//', $output, $m)) {
        return (float) $m[1];
    }

    return null;
}

// ── HTTPS Check ───────────────────────────────────────────────────────────────

/**
 * Checks an HTTP/HTTPS endpoint via cURL.
 * Follows redirects, verifies SSL, measures response time.
 *
 * @return array{status: string, detail: string}
 */
function check_https(string $host, int $timeout = 10): array
{
    // Prepend scheme if missing
    if (!preg_match('#^https?://#i', $host)) {
        $host = 'https://' . $host;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $host,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'Healthcheck-Monitor/1.0',
        CURLOPT_NOBODY         => false,
    ]);

    $startTime  = microtime(true);
    curl_exec($ch);
    $elapsed    = (microtime(true) - $startTime) * 1000;

    $httpCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return ['status' => 'down', 'detail' => $curlError];
    }

    if ($httpCode === 0) {
        return ['status' => 'down', 'detail' => 'No response'];
    }

    // 2xx and 3xx are considered up
    if ($httpCode >= 200 && $httpCode < 400) {
        return [
            'status' => 'up',
            'detail' => sprintf('HTTP %d – %.0f ms', $httpCode, $elapsed),
        ];
    }

    return [
        'status' => 'down',
        'detail' => sprintf('HTTP %d', $httpCode),
    ];
}

// ── TCP Port Check ────────────────────────────────────────────────────────────

/**
 * Checks whether a TCP port is open on the given host.
 *
 * @return array{status: string, detail: string}
 */
function check_port(string $host, int $port, int $timeout = 10): array
{
    if ($port < 1 || $port > 65535) {
        return ['status' => 'unknown', 'detail' => 'Invalid port: ' . $port];
    }

    $startTime = microtime(true);

    $socket = @stream_socket_client(
        sprintf('tcp://%s:%d', $host, $port),
        $errno,
        $errstr,
        $timeout
    );

    $elapsed = (microtime(true) - $startTime) * 1000;

    if ($socket === false) {
        $detail = $errstr !== '' ? $errstr : sprintf('Connection refused (errno %d)', $errno);
        return ['status' => 'down', 'detail' => $detail];
    }

    fclose($socket);

    return [
        'status' => 'up',
        'detail' => sprintf('Port %d open – %.0f ms', $port, $elapsed),
    ];
}
