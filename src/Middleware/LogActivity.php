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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        // modules live under Modules\<Name>\...; app controllers (App\Http\...) have no module
        $module_name = $action_name !== null && str_starts_with($action_name, 'Modules\\')
            ? explode('\\', $action_name)[1]
            : null;

        // get the current microtime for logging request-response time
        $start_time = microtime(true);

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
                status_code: $this->statusOf($e),
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
            response_body: $this->decodeJsonBody($response),
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
    ): void {
        try {
            // get the request payload and curl command
            $_payload = $this->requestPayload($request);
            $_curl = ActivityLogger::curlOfRequest($request);

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

    /**
     * The status the handler would answer with. Framework exceptions that are not HTTP exceptions
     * still have one: logging them as 500 would misreport every 401/403/404/422.
     */
    private function statusOf(Throwable $e): int
    {
        return match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof ValidationException => $e->status,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            default => 500,
        };
    }

    private function decodeJsonBody(Response $response): ?array
    {
        $content = $response->getContent();

        // streamed responses have no content in memory to log
        return is_string($content) ? LogBody::decodeJson($content) : null;
    }

    private function requestPayload(Request $request): array
    {
        // multipart bodies are not read as raw content: the parsed fields and file metadata are logged
        if (str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            return ActivityLogger::getPayloadOfRequest($request);
        }

        return ActivityLogger::getPayloadOfRequest($request);
    }
}
