<?php
namespace Yournamespace\Integrations\Meilisearch;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;

class CurlClient implements ClientInterface
{
    private static $ch = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (self::$ch === null) {
            self::$ch = curl_init();
        }

        $ch = self::$ch;

        curl_setopt($ch, CURLOPT_URL, (string)$request->getUri());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($request->getBody()->getSize() > 0) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$request->getBody());
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        }

        $responseStr = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $resHeadersStr = substr($responseStr, 0, $headerSize);
        $resBody = substr($responseStr, $headerSize);
        $responseHeaders = [];

        foreach (explode("\r\n", $resHeadersStr) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $responseHeaders[trim($key)] = trim($value);
            }
        }

        if (!str_ends_with(trim($resBody), '}')) {
            $resBody = trim($resBody) . '}';
        }

        return new Response($statusCode, $responseHeaders, $resBody);
    }

}
