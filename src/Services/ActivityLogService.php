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
use Illuminate\Database\Eloquent\Builder;
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
            // encrypt before dispatch: the queue payload never carries plaintext bodies
            if (config('activity-log.use_queue', true)) {
                $this->prepare($dto);

                if ($this->dispatchToQueue($dto)) {
                    return;
                }
            }

            // queue disabled or unavailable: store immediately (prepare() is skipped for a prepared DTO)
            $this->updateOrCreate($dto);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Returns false when the job could not be dispatched, so the caller stores the log synchronously.
     */
    private function dispatchToQueue(ActivityLogCreateDTO $dto): bool
    {
        try {
            StoreActivityLogJob::dispatch($dto)->onQueue(config('activity-log.queue_name', 'default'));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function updateOrCreate(ActivityLogCreateDTO $dto): void
    {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        $this->prepare($dto);
        $this->store($dto);
    }

    /**
     * Encrypts the DTO bodies, once. Runs before queueing; the queued job then skips it.
     */
    private function prepare(ActivityLogCreateDTO $dto): void
    {
        // event time is fixed here, before queueing, so queued writes keep the order the events happened in
        $dto->created_at ??= now()->format('Y-m-d H:i:s.u');

        if ($dto->prepared) {
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

        // file names and the date folder are fixed here: a retried job overwrites its own files instead of leaving orphans
        $fileKey = sha1($dto->additional_id);
        $dto->stored_on ??= now()->format('Y-m-d');

        if ($dto->request_body !== null) {
            $dto->request_body = FileStorage::storeEncrypted($this->encodeBody($dto->request_body), 'request_' . $fileKey, $dto->stored_on);
        }

        if ($dto->response_body !== null) {
            $dto->response_body = FileStorage::storeEncrypted($this->encodeBody($dto->response_body), 'response_' . $fileKey, $dto->stored_on);
        }

        if ($dto->curl) {
            $dto->curl = FileStorage::storeEncrypted($dto->curl, 'curl_' . $fileKey, $dto->stored_on);
        }

        $dto->prepared = true;
    }

    /**
     * Writes a prepared DTO: parent link and one atomic upsert.
     */
    private function store(ActivityLogCreateDTO $dto): void
    {
        $linked = $dto->model_id && $dto->model_type;

        // resolve the parent before the write, so parent_id is saved in the same statement
        if ($linked) {
            $dto->parent_id = $this->modelLogsQuery($dto)
                ->where('created_at', '<', $dto->created_at)
                ->latest('created_at')
                ->value('id') ?? $dto->parent_id;
        }

        // one atomic INSERT ... ON CONFLICT by additional_id: concurrent writers for the same request cannot create duplicates.
        // Only attributes present in the DTO are overwritten on conflict, so the middleware and model writes merge.
        // created_at is the event time and is only set on insert.
        $values = $dto->toArray();
        $now = now();

        ActivityLog::upsert(
            [array_merge(['id' => (string) Str::orderedUuid(), 'created_at' => $now, 'updated_at' => $now], $values)],
            ['additional_id'],
            [...array_diff(array_keys($values), ['additional_id', 'created_at']), 'updated_at'],
        );

        if ($linked) {
            $this->relinkNextLog($dto);
        }
    }

    /**
     * A log written late may sit between an existing pair: the next event then has to point to it.
     */
    private function relinkNextLog(ActivityLogCreateDTO $dto): void
    {
        $id = ActivityLog::query()->where('additional_id', $dto->additional_id)->value('id');

        $next = $this->modelLogsQuery($dto)
            ->where('created_at', '>', $dto->created_at)
            ->oldest('created_at')
            ->first(['id', 'parent_id']);

        if (! $next) {
            return;
        }

        $repoint = $next->parent_id === null
            || ActivityLog::query()->whereKey($next->parent_id)->where('created_at', '<', $dto->created_at)->exists();

        if ($repoint) {
            ActivityLog::query()->whereKey($next->id)->update(['parent_id' => $id]);
        }
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function modelLogsQuery(ActivityLogCreateDTO $dto): Builder
    {
        return ActivityLog::query()
            ->where('model_id', $dto->model_id)
            ->where('model_type', $dto->model_type)
            ->where('additional_id', '!=', $dto->additional_id);
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
            $url = $request->fullUrl();
            $headers = $request->headers->all();
            $body = $request->getContent();

            // form posts are parsed into $request->request and leave the raw content empty
            if ($body === '' && $request->request->count() > 0) {
                $body = http_build_query($request->request->all());
            }
        } else {
            // Handle Guzzle outgoing request
            $method = $request->getMethod();
            $url = (string) $request->getUri();
            $headers = $request->getHeaders();
            $body = (string) $request->getBody();
        }

        // Generate the curl command with headers and payload
        $curl = "curl -X {$method} '{$url}'";

        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                $curl .= " -H '" . $name . ': ' . $value . "'";
            }
        }

        if (! empty($body)) {
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
     * Stored bodies: invalid UTF-8 is substituted instead of silently turning the whole body into an empty string.
     */
    private function encodeBody(array|string $body): string
    {
        return json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }
}
