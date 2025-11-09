<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class AdminResourceCollection extends ResourceCollection
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
     * @param  \Illuminate\Http\Request  $request
     * @param  Response  $response
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}


