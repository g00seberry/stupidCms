<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Options\OptionsRepository;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\Options\IndexOptionsRequest;
use App\Http\Requests\Admin\Options\PutOptionRequest;
use App\Http\Resources\Admin\OptionCollection;
use App\Http\Resources\Admin\OptionResource;
use App\Models\Option;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class OptionsController extends Controller
{
    use AuthorizesRequests;
    use Problems;

    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    public function __construct(private readonly OptionsRepository $repository)
    {
    }

    public function index(IndexOptionsRequest $request, string $namespace): OptionCollection
    {
        $this->assertValidRouteParameters($namespace);

        $validated = $request->validated();

        $query = Option::query()->where('namespace', $namespace);

        $deleted = $validated['deleted'] ?? null;
        if ($deleted === 'with') {
            $query->withTrashed();
        } elseif ($deleted === 'only') {
            $query->onlyTrashed();
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($inner) use ($search) {
                $inner->where('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $options = $query
            ->orderBy('key')
            ->paginate($perPage);

        return new OptionCollection($options);
    }

    public function show(string $namespace, string $key): OptionResource
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('view', $option);

        return new OptionResource($option);
    }

    public function put(PutOptionRequest $request, string $namespace, string $key): OptionResource
    {
        $validated = $request->validated();

        $option = $request->option();
        $this->authorize('write', $option);

        $description = array_key_exists('description', $validated)
            ? $validated['description']
            : $option->description;

        $saved = $this->repository->set(
            $namespace,
            $key,
            $validated['value'],
            $description
        );

        return new OptionResource($saved);
    }

    public function destroy(string $namespace, string $key): Response
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('delete', $option);

        $deleted = $this->repository->delete($namespace, $key);

        if (! $deleted) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        return response()
            ->noContent()
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
    }

    public function restore(string $namespace, string $key): OptionResource
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::withTrashed()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('restore', $option);

        $restored = $option->trashed()
            ? $this->repository->restore($namespace, $key)
            : $option;

        if (! $restored) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        return new OptionResource($restored);
    }

    private function assertValidRouteParameters(string $namespace, ?string $key = null): void
    {
        $data = ['namespace' => $namespace];
        $rules = ['namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN]];

        if ($key !== null) {
            $data['key'] = $key;
            $rules['key'] = ['required', 'string', 'regex:' . self::KEY_PATTERN];
        }

        $validator = Validator::make($data, $rules);

        if (! $validator->fails()) {
            return;
        }

        throw new HttpResponseException($this->problem(
            422,
            'Validation error',
            'The provided option namespace/key is invalid.',
            [
                'type' => 'https://stupidcms.dev/problems/invalid-option-identifier',
                'code' => 'INVALID_OPTION_IDENTIFIER',
                'errors' => $validator->errors()->toArray(),
            ]
        ));
    }

    private function optionNotFoundProblem(string $namespace, string $key): JsonResponse
    {
        return $this->problem(
            404,
            'Option not found',
            sprintf('Option "%s/%s" was not found.', $namespace, $key),
            [
                'type' => 'https://stupidcms.dev/problems/not-found',
                'code' => 'NOT_FOUND',
            ]
        );
    }
}

