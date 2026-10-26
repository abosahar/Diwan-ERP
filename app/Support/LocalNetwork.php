<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Addresses other devices of the office network can open a local install on
 * (http://192.168.x.x:port), from this computer's private IPv4 addresses.
 */
class LocalNetwork
{
    /**
     * @return array<int, string>
     */
    public static function urls(Request $request): array
    {
        $port = $request->getPort();
        $suffix = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return array_map(fn ($ip) => "http://{$ip}{$suffix}", self::addresses());
    }

    /**
     * @return array<int, string>
     */
    public static function addresses(): array
    {
        $ips = gethostbynamel((string) gethostname()) ?: [];

        // The address of the interface that leads out of the computer (UDP "connect" sends nothing).
        $socket = @stream_socket_client('udp://192.0.2.1:9', $errno, $errstr, 1);
        if ($socket) {
            $ips[] = explode(':', (string) stream_socket_get_name($socket, false))[0];
            fclose($socket);
        }

        $private = array_filter(array_unique($ips), fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! str_starts_with($ip, '127.')
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE));

        return array_values($private);
    }
}
