<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTranslationRequest;
use App\Http\Requests\UpdateTranslationRequest;
use App\Models\TranslationKey;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Schema(
    schema: 'Translation',
    type: 'object',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'welcome.title'),
        new OA\Property(
            property: 'translations',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
            example: ['en' => 'Welcome', 'fr' => 'Bienvenue'],
        ),
        new OA\Property(
            property: 'tags',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['web', 'marketing'],
        ),
    ],
)]
class TranslationController extends Controller
{
    public function __construct(private readonly TranslationService $service) {}

    #[OA\Get(
        path: '/translations/{key}',
        summary: 'Get a single translation key',
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/Translation')),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(string $key): JsonResponse
    {
        return response()->json(
            $this->present($this->service->find($key)),
        );
    }

    #[OA\Post(
        path: '/translations',
        summary: 'Create a translation key with one or more locale values',
        tags: ['Translations'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['key', 'translations'],
                properties: [
                    new OA\Property(property: 'key', type: 'string', example: 'welcome.title'),
                    new OA\Property(
                        property: 'translations',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(type: 'string'),
                        example: ['en' => 'Welcome', 'fr' => 'Bienvenue'],
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['web'],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Translation')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $model = $this->service->create(
            $request->string('key')->toString(),
            $request->array('translations'),
            $request->array('tags'),
        );

        Cache::flush();

        return response()->json($this->present($model), Response::HTTP_CREATED);
    }

    #[OA\Put(
        path: '/translations/{key}',
        summary: 'Update locale values and (optionally) tags for an existing key',
        tags: ['Translations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['translations'],
                properties: [
                    new OA\Property(
                        property: 'translations',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(type: 'string'),
                        example: ['en' => 'Welcome back'],
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string'),
                        example: ['web', 'returning'],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/Translation')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(UpdateTranslationRequest $request, string $key): JsonResponse
    {
        $model = $this->service->update(
            $key,
            $request->array('translations'),
            $request->has('tags') ? $request->array('tags') : null,
        );

        Cache::flush();

        return response()->json($this->present($model));
    }

    #[OA\Get(
        path: '/translations',
        summary: 'Search translation keys',
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'content', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'locale', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags[]', in: 'query', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 50, minimum: 1, maximum: 200)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of keys',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Translation')),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $page = $this->service->search([
            'key' => $request->string('key')->toString() ?: null,
            'content' => $request->string('content')->toString() ?: null,
            'locale' => $request->string('locale')->toString() ?: null,
            'tags' => (array) $request->input('tags', []),
        ], $perPage);

        return response()->json([
            'data' => array_map(
                fn (TranslationKey $k): array => $this->present($k),
                $page->items(),
            ),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/translations/export',
        summary: 'Export all translations grouped by locale (cached, 60s TTL)',
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'query', schema: new OA\Schema(type: 'string'), example: 'en'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(type: 'string'),
                    ),
                    example: [
                        'en' => ['welcome.title' => 'Welcome'],
                        'fr' => ['welcome.title' => 'Bienvenue'],
                    ],
                ),
            ),
        ],
    )]
    public function export(Request $request): JsonResponse
    {
        // Large dataset export is constrained to a single locale to reduce payload size and improve encoding performance
        $locale = $request->string('locale')->toString();

        if (empty($locale)) {
            return response()->json([
                'error' => 'locale is required for export'
            ], 422);
        }

        $cacheKey = 'translations.export.'.$locale;

        $data = Cache::remember($cacheKey, 60, function () use ($locale) {
            return $this->service->export($locale);
        });
        return response()->json($data);
    }

    private function present(TranslationKey $model): array
    {
        return [
            'key' => $model->key,
            'translations' => $model->values
                ->mapWithKeys(fn ($v) => [$v->locale => $v->content])
                ->all(),
            'tags' => $model->tags->pluck('name')->all(),
        ];
    }
}
