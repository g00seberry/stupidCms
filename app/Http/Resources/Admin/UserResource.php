<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;

class UserResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param User $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => (int) $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }
}

