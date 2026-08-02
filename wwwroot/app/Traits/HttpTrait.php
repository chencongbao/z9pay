<?php

namespace App\Traits;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait HttpTrait
{


    public function postData($url, $data = [], $options = [])
    {
        try {
            $headers = $options['header'] ?? [];
            $model = $options['mode'] ?? "json";
            $httpOptions = Arr::except($options, ['header', 'mode']);

            $request = Http::withOptions(array_filter(Arr::collapse([['handler' => $this->stack()], $httpOptions])));

            switch ($model) {
                case "json":
                    $request = $request->asJson();
                    break;
                case "form":
                    $request = $request->asForm();
                    break;
                case "cjson": //自定义json
                    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $request =  $request->withHeaders(Arr::collapse([$headers, ['Content-Type' => 'application/json']]))->withBody($json, 'application/json');
                    break;
            }
            return $request->withHeaders($headers)->post($url, $data);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }

    public function getData($url, $data = [], $options = [])
    {
        try {
            $headers = $options['header'] ?? [];
            $model = $options['mode'] ?? "json";
            switch ($model) {
                case "json":
                    $headers = Arr::collapse([$headers,['Content-Type'=>'application/json']]);
                    break;
                case "form":
                    $headers = Arr::collapse([$headers,['Content-Type'=>'application/x-www-form-urlencoded']]);
                    break;
            }
            return Http::withOptions(array_filter(['handler' => $this->stack()]))->withHeaders($headers)->get($url, $data);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }



    public function stack()
    {
        $stack = HandlerStack::create(new CurlHandler());
        // 日志文件路径
        $logDir = storage_path('logs' . DIRECTORY_SEPARATOR . date('Y-m-d'));
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        $logPath = $logDir . DIRECTORY_SEPARATOR . 'http.log';

        $formatRequest = function (RequestInterface $request): array {
            $uri = $request->getUri();
            $path = $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');

            $stream = $request->getBody();
            $rawReq = (string)$stream;
            if ($stream->isSeekable()) {
                $stream->rewind();
            } else {
                $request = $request->withBody(Utils::streamFor($rawReq));
            }

            $reqHeaders = [];
            foreach ($request->getHeaders() as $k => $vs) {
                foreach ($vs as $v) $reqHeaders[] = "$k: $v";
            }

            $text = sprintf(
                "Request %s\n%s %s HTTP/%s\r\n%s\r\n\r\n%s\r\n",
                (string)$uri,
                $request->getMethod(),
                $path,
                $request->getProtocolVersion(),
                implode("\r\n", $reqHeaders),
                $rawReq
            );

            return [$request, $text];
        };

        $formatResponse = function (ResponseInterface $response): array {
            $resHeaders = [];
            foreach ($response->getHeaders() as $k => $vs) {
                foreach ($vs as $v) $resHeaders[] = "$k: $v";
            }

            $s = $response->getBody();
            $rawResp = (string)$s;
            if ($s->isSeekable()) $s->rewind();
            else $response = $response->withBody(Utils::streamFor($rawResp));

            $text = sprintf(
                "--------------------\nHTTP/%s %s %s\r\n%s\r\n\r\n%s",
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                implode("\r\n", $resHeaders),
                $rawResp
            );

            return [$response, $text];
        };

        $stack->push(function ($handler) use ($logPath, $formatRequest, $formatResponse) {
            return function (RequestInterface $request, array $options) use ($handler, $logPath, $formatRequest, $formatResponse) {
                [$request, $reqText] = $formatRequest($request);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($logPath, $reqText, $formatResponse) {
                        [, $resText] = $formatResponse($response);
                        Log::build(['driver' => 'single', 'path' => $logPath])->info($reqText . $resText);
                        return $response;
                    },
                    function ($reason) use ($logPath, $reqText) {
                        if ($reason instanceof RequestException && $reason->hasResponse()) {
                            $response = $reason->getResponse();
                            $s = $response->getBody();
                            $rawResp = (string)$s;
                            if ($s->isSeekable()) {
                                $s->rewind();
                            } else {
                                $response = $response->withBody(Utils::streamFor($rawResp));
                            }
                            $resHeaders = [];
                            foreach ($response->getHeaders() as $k => $vs) {
                                foreach ($vs as $v) $resHeaders[] = "$k: $v";
                            }
                            $resText = sprintf(
                                "--------------------\nHTTP/%s %s %s\r\n%s\r\n\r\n%s",
                                $response->getProtocolVersion(),
                                $response->getStatusCode(),
                                $response->getReasonPhrase(),
                                implode("\r\n", $resHeaders),
                                $rawResp
                            );
                            Log::build(['driver' => 'single', 'path' => $logPath])->error($reqText . $resText);
                            return $response;
                        }

                        $msg = $reason instanceof \Throwable ? $reason->getMessage() : (string)$reason;
                        $log = $reqText . "\n--------------------\nHTTP/ERROR\n" . $msg;
                        Log::build(['driver' => 'single', 'path' => $logPath])->error($log);

                        // ⚠️ 不抛异常，返回一个假的 Response（状态码 599 表示网络错误）
                        return new Response(
                            599,
                            ['Content-Type' => 'text/plain'],
                            "Request failed: " . $msg
                        );
                    }
                );
            };
        });

        return $stack;
    }
}
