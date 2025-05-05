<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-05-01
 * Contact: https://t.me/alif_coder
 * Time: 10:49 AM
 */

namespace Alif\ActivityLog\Middleware;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Route;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        // generate a unique request ID
        $request_id = (string)\Str::uuid();
        // set the request ID in the request attributes
        $request->attributes->set('activity_log_id', $request_id);

        // get the current route and action name
        $route       = Route::getCurrentRoute();
        $action_name = $route?->getActionName();
        // get the module name from the action name
        $module_name = explode("\\", $action_name)[1] ?? null;

        // get response from the next middleware
        $response = $next($request);

        // get the request payload, response body and curl command
        $_payload  = ActivityLogger::getPayloadOfRequest($request);
        $_response = json_decode($response->getContent(), true);
        $_curl     = ActivityLogger::curlOfRequest($request);

        // create a DTO for the activity log
        $dto = new ActivityLogCreateDTO(
                additional_id: $request_id,
                log_type:      'middleware',
                user_id:       $request->user()?->id,
                module:        $module_name,
                route:         $action_name,
                url:           $request->fullUrl(),
                user_agent:    $request->header('User-Agent'),
                app_type:      $request->header('App-Type'),
                app_version:   $request->header('App-Version'),
                ip:            $request->ip(),
                method:        $request->method(),
                status_code:   $response->getStatusCode(),
                request_body:  $_payload,
                response_body: $_response,
                curl:          $_curl,
                description:   "Middleware log",
        );

        // log the activity
        ActivityLogger::log($dto);

        // return the response
        return $response;
    }
}