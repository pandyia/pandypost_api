<?php

namespace App\Services;

use App\Events\WorkspaceEvent;
use App\Exceptions\WorkspaceException;
use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkspaceService extends BaseService
{
    // protected array $with = ['accesses'];

    private const AVATAR_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(Workspace $workspace, private readonly StorageService $storage)
    {
        parent::__construct($workspace);
    }

    /**
     * @return array{url: string, headers: array, path: string, content_type: string, max_size: int, expires_in: int}
     */
    public function generateAvatarUploadUrl(Workspace $workspace, string $contentType): array
    {
        return $this->storage->generateWorkspaceAvatarUploadUrl(
            $workspace->uuid,
            $contentType,
            self::AVATAR_MIME_TYPES[$contentType],
        );
    }

    /**
     * Confirma o upload da foto: confere o arquivo de verdade (o PUT pré-assinado não limita
     * tipo nem tamanho), troca a foto, apaga a anterior e avisa os membros.
     */
    public function setAvatar(Workspace $workspace, string $path): Workspace
    {
        $maxSize = (int) config('services.s3.avatar_max_size', 5242880);

        if (! array_key_exists($this->storage->mimeType($path), self::AVATAR_MIME_TYPES)) {
            $this->storage->delete($path);

            throw WorkspaceException::invalidAvatarFile();
        }

        if ($this->storage->size($path) > $maxSize) {
            $this->storage->delete($path);

            throw WorkspaceException::avatarTooLarge($maxSize);
        }

        $workspace->update([
            'avatar_path' => $this->storage->replaceFile($workspace->avatar_path, $path),
        ]);

        event(new WorkspaceEvent($workspace->refresh()));

        return $workspace;
    }

    public function removeAvatar(Workspace $workspace): Workspace
    {
        if ($workspace->avatar_path) {
            $this->storage->delete($workspace->avatar_path);
            $workspace->update(['avatar_path' => null]);

            event(new WorkspaceEvent($workspace->refresh()));
        }

        return $workspace->refresh();
    }

    public function store(array $data): Model
    {
        $user = auth()->user();

        $alreadyExists = $user->accesses()
            ->whereHas('workspace', fn ($q) => $q->where('name', $data['name']))
            ->exists();

        if ($alreadyExists) {
            throw WorkspaceException::nameAlreadyExists();
        }

        // Só o nome vem do usuário; is_personal_team/role_name/permissions são
        // exclusivos do workspace pessoal criado pelo UserObserver.
        return $this->createAllAccess($user, ['name' => $data['name']]);
    }

    public function createAllAccess(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'is_personal_team' => $data['is_personal_team'] ?? false,
            ]);

            $role = Role::create([
                'name' => $data['role_name'] ?? 'Admin',
                'workspace_id' => $workspace->id,
            ]);

            $role->permissions()->syncWithoutDetaching($data['permissions'] ?? Role::adminPermissions());

            Access::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role_id' => $role->id,
            ]);

            return $workspace;
        });
    }

    public function destroy(int|string $id): void
    {
        $entity = $this->findById($id);

        if ($entity->is_personal_team) {
            throw WorkspaceException::personalTeamCannotDeleted();
        }

        $entity->delete();
    }

    public function destroyByUuid(string $uuid): void
    {
        $entity = $this->findByUuid($uuid);

        if ($entity->is_personal_team) {
            throw WorkspaceException::personalTeamCannotDeleted();
        }

        $entity->delete();
    }

    public function getAllSystemWorkspaces()
    {
        return $this->model->allAccess()->get();
    }

    public function switchWorkspace(User $user, string $workspaceUuid): Access
    {
        $workspace = Workspace::where('uuid', $workspaceUuid)->firstOrFail();

        $access = $user->accesses()
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $access) {
            throw WorkspaceException::userNotLinked();
        }

        $user->update(['access_id' => $access->id]);
        $user->unsetRelation('currentAccess');

        return $access->load('role.permissions', 'workspace');
    }
}
