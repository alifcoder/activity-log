<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 5:15 PM
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Jobs\StoreActivityLogJob;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Services\Interface\ActivityLogServiceInterface;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\RequestInterface as PsrRequest;

class ActivityLogService implements ActivityLogServiceInterface
{


    public function log(ActivityLogCreateDTO $dto): void
    {
        // check if the activity log should be stored in the queue
        if (config('activity-log.use_queue', true)) {
            StoreActivityLogJob::dispatchAfterResponse($dto)->onQueue(config('activity-log.queue_name', 'default'));
        } else {
            // Store the activity log immediately
            $this->updateOrCreate($dto);
        }
    }

    public function updateOrCreate(ActivityLogCreateDTO $dto): void
    {
        // update or create log in the database by additional_id
        $log = ActivityLog::updateOrCreate(
                [
                        'additional_id' => $dto->additional_id,
                ],
                $dto->toArray()
        );

        // if the log has model id and model type, find the parent log
        if ($log->model_id && $log->model_type) {
            $parent = ActivityLog::where('model_id', $log->model_id)
                    ->where('model_type', $log->model_type)
                    ->where('id', '!=', $log->id)
                    ->latest()
                    ->first();
            // if parent log exists, set the parent_id of the current log
            if ($parent) {
                $log->parent_id = $parent->id;
                $log->save();
            }
        }
    }

    public function curlOfRequest($request): ?string
    {
        if ($request instanceof LaravelRequest) {
            // Handle Laravel incoming request
            $contentType = $request->header('Content-Type');
            $method      = $request->method();
            $url         = $request->fullUrl();
            $headers     = $request->headers->all();
            $body        = $request->getContent();
        } elseif ($request instanceof PsrRequest) {
            // Handle Guzzle outgoing request
            $contentType = $request->getHeaderLine('Content-Type');
            $method      = $request->getMethod();
            $url         = (string)$request->getUri();
            $headers     = $request->getHeaders();
            $body        = (string)$request->getBody();
        } else {
            // Unknown type
            return null;
        }

        // Ignore multipart/form-data requests
        if ($contentType && str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            return null;
        }

        // Generate the curl command with headers and payload
        $curl = "curl -X {$method} '{$url}'";

        foreach ($headers as $name => $values) {
            foreach ((array)$values as $value) {
                $curl .= " -H '" . $name . ": " . $value . "'";
            }
        }

        if (!empty($body)) {
            // Escape single quotes only, since we wrap the body in single quotes
            $escapedBody = str_replace("'", "'\"'\"'", $body);
            $curl        .= " --data '{$escapedBody}'";
        }

        return $curl;
    }

    public function getPayloadOfRequest(LaravelRequest $request): array
    {
        $payload = [];

        if (str_contains($request->header('Content-Type'), 'multipart/form-data')) {
            // Only log form fields, not files
            $payload['fields'] = $request->except(array_keys($request->files->all()));
            $payload['files']  = collect($request->files)->map(function ($file) {
                $file = $file['file'] ?? null; // Handle the case where file is wrapped in an array

                /** @var ?UploadedFile $file */
                return [
                        'name' => $file?->getClientOriginalName(),
                        'mime' => $file?->getMimeType(),
                        'size' => $file?->getSize(),
                ];
            })->toArray();
        } else {
            // Safe for application/json or x-www-form-urlencoded
            $payload = $request->all();
        }

        return $payload;
    }
}