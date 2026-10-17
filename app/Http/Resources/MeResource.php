<?php

namespace App\Http\Resources;

use App\Models\Access;
use App\Models\Permission;
use App\Services\Storage\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $access = $this->resolveCurrentAccess();

        $permissions = $this->resolvePermissions($access);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'avatar' => $this->avatarUrl(),
            'phone' => $this->phone,
            'bio' => $this->bio,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at?->toIso8601String(),
            'is_super_admin' => $this->is_super_admin,
            'current_workspace' => $this->when($access, fn () => [
                'uuid' => $access->workspace?->uuid,
                'name' => $access->workspace?->name,
            ]),
            'role' => $this->when($access, fn () => $access->role?->name),
            'permissions' => $permissions,
        ];
    }

    /** URL assinada e longa da foto (muda a cada chamada); o caminho no S3 nunca sai. */
    private function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return app(StorageService::class)->generateDownloadUrl(
            $this->avatar_path,
            (int) config('services.s3.presigned_avatar_ttl', 518400),
        );
    }

    private function resolvePermissions(?Access $access): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::all()
                ->pluck('name')
                ->all();
        }

        if (! $access || ! $access->role) {
            return [];
        }

        return $access->role->permissions
            ->pluck('name')
            ->all();
    }
}
