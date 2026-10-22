<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 4:40 PM
 */

namespace Alif\ActivityLog\Macros;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Support\LogBody;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

class HttpMacro
{
    public static function register(): void
    {
        Http::macro('loggable', function (string $log_type = 'http', string $description = 'Http log') {
            return Http::withMiddleware(function (callable $handler) use ($log_type, $description) {
                return function (RequestInterface $request, array $options) use ($handler, $log_type, $description) {
                    if (! config('activity-log.enabled', true)) {
                        return $handler($request, $options);
                    }

                    // Start timing
                    $start_time = microtime(true);

                    // Generate a unique request ID
                    $request_id = (string) \Str::uuid();
                    $payloadArray = [];

                    $contentType = $request->getHeaderLine('Content-Type');
                    // skip if the request is a file upload
                    if (! str_contains($contentType, 'multipart/form-data')) {
                        $bodyStream = $request->getBody();
                        $bodyStream->rewind(); // Rewind before reading
                        $payloadArray = LogBody::decodeJson($bodyStream->getContents()) ?? [];
                    }

                    // Create a DTO for the activity log
                    $dto = new ActivityLogCreateDTO(
                        additional_id: $request_id,
                        log_type: $log_type,
                        user_id: auth()->user() ? (string) auth()->user()->id : null,
                        url: (string) $request->getUri(),
                        method: $request->getMethod(),
                        request_body: $payloadArray,
                        curl: ActivityLogger::curlOfRequest($request),
                        description: $description,
                    );

                    // Log the request
                    ActivityLogger::log($dto);

                    // Make the actual request
                    return $handler($request, $options)->then(
                        function ($response) use ($request_id, $start_time) {
                            // Duration
                            $duration = (microtime(true) - $start_time) * 1000; // in milliseconds

                            // Peak Memory
                            $memory = memory_get_peak_usage(true);

                            // Get the response body and content type
                            $contentType = $response->getHeaderLine('Content-Type');
                            $response_body = [];
                            // Skip binary or file responses
                            if (str_starts_with($contentType, 'application/json') || str_starts_with($contentType, 'text/')) {
                                $response_body = LogBody::decodeJson((string) $response->getBody()) ?? [];
                            }
                            // Create a DTO for the activity log
                            $dto = new ActivityLogCreateDTO(
                                additional_id: $request_id,
                                status_code: (string) $response->getStatusCode(),
                                response_body: $response_body,
                                request_duration: $duration,
                                peak_memory_usage: $memory,
                            );
                            // Update the activity log with the response
                            ActivityLogger::log($dto);

                            return $response;
                        },
                        // failed calls (timeouts, connection errors) still end the row; the status is kept when the server answered
                        function ($reason) use ($request_id, $start_time) {
                            $response = $reason instanceof BadResponseException ? $reason->getResponse() : null;

                            ActivityLogger::log(new ActivityLogCreateDTO(
                                additional_id: $request_id,
                                status_code: $response ? (string) $response->getStatusCode() : null,
                                request_duration: (microtime(true) - $start_time) * 1000,
                                peak_memory_usage: memory_get_peak_usage(true),
                            ));

                            // the caller still receives the original failure
                            throw $reason;
                        },
                    );
                };
            });
        });
    }
}
