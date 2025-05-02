<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 4:40 PM
 */

namespace Alif\ActivityLog\Macros;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

class HttpMacro
{
    public static function register(): void
    {
        Http::macro('loggable', function (string $log_type = 'http') {
            return Http::withMiddleware(function (callable $handler) use ($log_type) {
                return function (RequestInterface $request, array $options) use ($handler, $log_type) {
                    // Generate a unique request ID
                    $request_id   = (string)\Str::uuid();
                    $payloadArray = [];

                    $contentType = $request->getHeaderLine('Content-Type');
                    // skip if the request is a file upload
                    if (!str_contains($contentType, 'multipart/form-data')) {
                        $bodyStream = $request->getBody();
                        $bodyStream->rewind(); // Rewind before reading
                        $payloadArray = json_decode($bodyStream->getContents(), true) ?? [];
                    }

                    // Create a DTO for the activity log
                    $dto = new ActivityLogCreateDTO(
                            additional_id: $request_id,
                            log_type:      $log_type,
                            user_id:       auth()->check() ? auth()->user()?->id : null,
                            url:           (string)$request->getUri(),
                            method:        $request->getMethod(),
                            request_body:  $payloadArray,
                            curl:          ActivityLogger::curlOfRequest($request)
                    );

                    // Log the request
                    ActivityLogger::log($dto);

                    // Make the actual request
                    return $handler($request, $options)->then(
                            function ($response) use ($request, $request_id) {
                                // Get the response body and content type
                                $contentType   = $response->getHeaderLine('Content-Type');
                                $response_body = [];
                                // Skip binary or file responses
                                if (str_starts_with($contentType, 'application/json') || str_starts_with($contentType, 'text/')) {
                                    $response_body = json_decode($response->getBody(), true) ?? [];
                                }
                                // Create a DTO for the activity log
                                $dto = new ActivityLogCreateDTO(
                                        additional_id: $request_id,
                                        status_code:   $response->getStatusCode(),
                                        response_body: $response_body,
                                );
                                // Update the activity log with the response
                                ActivityLogger::log($dto);

                                return $response;
                            }
                    );
                };
            });
        });
    }
}