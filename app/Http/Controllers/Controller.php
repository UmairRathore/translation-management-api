<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Translation Management API',
    description: 'Read-heavy translation management service: keys, locales, tags, and an optimized export endpoint.',
)]
#[OA\Server(url: '/api', description: 'Default API server')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum personal access token',
)]
abstract class Controller
{
    //
}
