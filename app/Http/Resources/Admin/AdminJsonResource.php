<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

abstract class AdminJsonResource extends JsonResource
{
    use ConfiguresAdminResponse;

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Response  $response
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * Точка расширения для потомков.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Response  $response
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}


