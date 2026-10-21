<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 10:49 AM
 */

namespace Alif\ActivityLog\Middleware;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Alif\ActivityLog\Support\LogBody;
use Closure;
use Illuminate\Http\Request;
use Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('activity-log.enabled', true)) {
            return $next($request);
        }

        // generate a unique request ID
        $request_id = (string) \Str::uuid();
        // set the request ID in the request attributes
        $request->attributes->set('activity_log_id', $request_id);

        // get the current route and action name
        $route = Route::getCurrentRoute();
        $action_name = $route?->getActionName();
        // get the module name from the action name
        $module_name = explode('\\', $action_name)[1] ?? null;

        // get the current microtime for logging request-response time
        $start_time = microtime(true);

        // file uploads and exports: only the metadata is logged, never the bodies
        $skipBodies = $this->bodiesSkipped($request);

        // get response from the next middleware
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // log failed requests too, then let the exception continue to the handler
            $this->record(
                request: $request,
                request_id: $request_id,
                action_name: $action_name,
                module_name: $module_name,
                start_time: $start_time,
                status_code: $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500,
                skipBodies: $skipBodies,
            );

            throw $e;
        }

        $this->record(
            request: $request,
            request_id: $request_id,
            action_name: $action_name,
            module_name: $module_name,
            start_time: $start_time,
            status_code: $response->getStatusCode(),
            response_body: $skipBodies ? null : $this->decodeJsonBody($response),
            skipBodies: $skipBodies,
        );

        // return the response
        return $response;
    }

    private function record(
        Request $request,
        string $request_id,
        ?string $action_name,
        ?string $module_name,
        float $start_time,
        int $status_code,
        ?array $response_body = null,
        bool $skipBodies = false,
    ): void {
        try {
            // get the request payload and curl command
            $_payload = $skipBodies ? null : $this->requestPayload($request);
            $_curl = $skipBodies ? null : ActivityLogger::curlOfRequest($request);

            // calculate the time taken for the request-response cycle
            $end_time = microtime(true);
            $duration = ($end_time - $start_time) * 1000; // in milliseconds

            // calculate the peak memory used during the request-response cycle
            $peak_memory_used = memory_get_peak_usage(true); // in bytes

            // create a DTO for the activity log
            $dto = new ActivityLogCreateDTO(
                additional_id: $request_id,
                log_type: 'middleware',
                user_id: $request->user() ? (string) $request->user()->id : null,
                module: $module_name,
                route: $action_name,
                url: $request->fullUrl(),
                user_agent: $request->header('User-Agent'),
                app_type: $request->header('App-Type'),
                app_version: $request->header('App-Version'),
                ip: $request->ip(),
                method: $request->method(),
                status_code: (string) $status_code,
                request_body: $_payload,
                response_body: $response_body,
                curl: $_curl,
                description: 'Middleware log',
                request_duration: $duration,
                peak_memory_usage: $peak_memory_used,
            );

            // log the activity
            ActivityLogger::log($dto);
        } catch (Throwable $e) {
            // logging must never break the request
            report($e);
        }
    }

    private function decodeJsonBody(Response $response): ?array
    {
        $content = $response->getContent();

        // streamed responses have no content in memory to log
        return is_string($content) ? LogBody::decodeJson($content) : null;
    }

    /** Paths from `activity-log.skip_body_paths` (Str::is patterns) are logged without their bodies. */
    private function bodiesSkipped(Request $request): bool
    {
        return $request->is(...(array) config('activity-log.skip_body_paths', []));
    }

    private function requestPayload(Request $request): array
    {
        // multipart bodies are not read as raw content: the parsed fields and file metadata are logged
        if (str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            return ActivityLogger::getPayloadOfRequest($request);
        }

        $content = (string) $request->getContent();

        return LogBody::isOversized($content)
                ? ['_truncated' => strlen($content)]
                : ActivityLogger::getPayloadOfRequest($request);
    }
}
