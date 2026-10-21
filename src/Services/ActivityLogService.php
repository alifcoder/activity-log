<?php

/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 5:15 PM
 */

namespace Alif\ActivityLog\Services;

use Alif\ActivityLog\DTO\ActivityLogCreateDTO;
use Alif\ActivityLog\Facades\FileStorage;
use Alif\ActivityLog\Jobs\StoreActivityLogJob;
use Alif\ActivityLog\Models\ActivityLog;
use Alif\ActivityLog\Services\Interface\ActivityLogServiceInterface;
use Alif\ActivityLog\Support\LogBody;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Throwable;

class ActivityLogService implements ActivityLogServiceInterface
{
    public function log(ActivityLogCreateDTO $dto): void
    {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        // logging must never break the application: report and continue
        try {
            // check if the activity log should be stored in the queue
            if (config('activity-log.use_queue', true)) {
                StoreActivityLogJob::dispatch($dto)->onQueue(config('activity-log.queue_name', 'default'));
            } else {
                // Store the activity log immediately
                $this->updateOrCreate($dto);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function updateOrCreate(ActivityLogCreateDTO $dto): void
    {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        // a manual log without an id gets its own row; a shared null id would merge unrelated logs
        $dto->additional_id ??= (string) Str::uuid();

        if (is_array($dto->request_body) && empty($dto->request_body)) {
            $dto->request_body = null;
        }

        if (is_array($dto->response_body) && empty($dto->response_body)) {
            $dto->response_body = null;
        }

        if (is_array($dto->request_body)) {
            $dto->request_body = $this->redact($dto->request_body);
        }

        if (is_array($dto->response_body)) {
            $dto->response_body = $this->redact($dto->response_body);
        }

        // file names derive from additional_id: a retried job overwrites its own files instead of leaving orphans
        $fileKey = sha1($dto->additional_id);

        if ($dto->request_body !== null) {
            $dto->request_body = FileStorage::storeEncrypted($this->encodeBody($dto->request_body), 'request_' . $fileKey);
        }

        if ($dto->response_body !== null) {
            $dto->response_body = FileStorage::storeEncrypted($this->encodeBody($dto->response_body), 'response_' . $fileKey);
        }

        // the url may carry secrets in its query string
        if ($dto->url) {
            $dto->url = $this->redactUrl($dto->url);
        }

        if ($dto->curl) {
            $dto->curl = FileStorage::storeEncrypted($dto->curl, 'curl_' . $fileKey);
        }

        // resolve the parent before the write, so parent_id is saved in the same statement
        if ($dto->model_id && $dto->model_type) {
            $dto->parent_id = ActivityLog::query()
                ->where('model_id', $dto->model_id)
                ->where('model_type', $dto->model_type)
                ->where('additional_id', '!=', $dto->additional_id)
                ->latest('created_at')
                ->value('id') ?? $dto->parent_id;
        }

        // one atomic INSERT ... ON CONFLICT by additional_id: concurrent writers for the same request cannot create duplicates.
        // Only attributes present in the DTO are overwritten on conflict, so the middleware and model writes merge.
        $values = $dto->toArray();
        $now = now();

        ActivityLog::upsert(
            [array_merge($values, ['id' => (string) Str::orderedUuid(), 'created_at' => $now, 'updated_at' => $now])],
            ['additional_id'],
            [...array_diff(array_keys($values), ['additional_id']), 'updated_at'],
        );
    }

    public function curlOfRequest($request): ?string
    {
        if ($request instanceof LaravelRequest) {
            $contentType = $request->header('Content-Type');
        } elseif ($request instanceof PsrRequest) {
            $contentType = $request->getHeaderLine('Content-Type');
        } else {
            // Unknown type
            return null;
        }

        // multipart bodies carry files: they are ignored before the body is read, so an upload is never loaded whole
        if ($contentType && str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            return null;
        }

        if ($request instanceof LaravelRequest) {
            // Handle Laravel incoming request
            $method = $request->method();
            $url = $this->redactUrl($request->fullUrl());
            $headers = $request->headers->all();
            $body = $request->getContent();

            // form posts are parsed into $request->request and leave the raw content empty
            if ($body === '' && $request->request->count() > 0) {
                $body = http_build_query($request->request->all());
            }
        } else {
            // Handle Guzzle outgoing request
            $method = $request->getMethod();
            $url = $this->redactUrl((string) $request->getUri());
            $headers = $request->getHeaders();
            $body = (string) $request->getBody();
        }

        // Generate the curl command with headers and payload
        $curl = "curl -X {$method} '{$url}'";

        $redactHeaders = array_map('strtolower', config('activity-log.redact_headers', []));

        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                if (in_array(strtolower($name), $redactHeaders, true)) {
                    $value = '[REDACTED]';
                }

                $curl .= " -H '" . $name . ': ' . $value . "'";
            }
        }

        if (! empty($body)) {
            // oversized bodies are not parsed, masked or stored; only their size is kept
            $body = LogBody::isOversized($body)
                    ? '[TRUNCATED: ' . strlen($body) . ' bytes]'
                    : $this->redactBody($body, $contentType);

            // Escape single quotes only, since we wrap the body in single quotes
            $escapedBody = str_replace("'", "'\"'\"'", $body);
            $curl .= " --data '{$escapedBody}'";
        }

        return $curl;
    }

    public function getPayloadOfRequest(LaravelRequest $request): array
    {
        $payload = [];

        if (str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            // Only log form fields, not files
            $payload['fields'] = $request->except(array_keys($request->files->all()));
            $payload['files'] = collect($request->files)->map(function ($file) {
                if (is_array($file)) {
                    $file = $file['file'] ?? null; // Handle the case where file is wrapped in an array
                }

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

    /**
     * Mask sensitive values in a raw request body: JSON, or form-urlencoded
     * (keeps the original encoding of untouched pairs). Other bodies are returned as-is.
     */
    private function redactBody(string $body, ?string $contentType): string
    {
        $decodedBody = json_decode($body, true);

        if (is_array($decodedBody)) {
            return json_encode($this->redact($decodedBody), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($contentType && str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            return $this->redactPairs($body);
        }

        return $body;
    }

    /**
     * Replace values of sensitive keys (case-insensitive, nested) with [REDACTED].
     */
    private function redact(array $data): array
    {
        $redactKeys = array_map('strtolower', config('activity-log.redact_keys', []));

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $redactKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * Mask sensitive values of a url's query string. Fragment and path are kept.
     */
    private function redactUrl(string $url): string
    {
        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        if (($question = strpos($url, '?')) === false) {
            return $url . $fragment;
        }

        return substr($url, 0, $question + 1) . $this->redactPairs(substr($url, $question + 1)) . $fragment;
    }

    /**
     * Mask values of sensitive keys in a raw "a=1&b=2" string, keeping the original encoding of other pairs.
     * Nested keys such as user[password] are split into segments; any sensitive segment matches.
     */
    private function redactPairs(string $raw): string
    {
        $redactKeys = array_map('strtolower', config('activity-log.redact_keys', []));

        return implode('&', array_map(function (string $pair) use ($redactKeys) {
            [$key] = array_pad(explode('=', $pair, 2), 1, '');

            preg_match_all('/[^\[\]]+/', urldecode($key), $segments);
            $sensitive = array_intersect(array_map('strtolower', $segments[0]), $redactKeys);

            return $sensitive ? "{$key}=%5BREDACTED%5D" : $pair;
        }, explode('&', $raw)));
    }

    /**
     * Stored bodies: invalid UTF-8 is substituted instead of silently turning the whole body into an empty string.
     */
    private function encodeBody(array|string $body): string
    {
        return json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }
}
