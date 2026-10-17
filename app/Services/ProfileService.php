<?php

namespace App\Services;

use App\Exceptions\ProfileException;
use App\Models\User;
use App\Services\Storage\StorageService;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileService
{
    private const AVATAR_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly StorageService $storage,
    ) {}

    /**
     * @param  array{name: string, phone?: ?string, bio?: ?string, timezone: string}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'bio' => $data['bio'] ?? null,
            'timezone' => $data['timezone'],
        ]);

        return $user->refresh();
    }

    /**
     * @return array{url: string, headers: array, path: string, content_type: string, max_size: int, expires_in: int}
     */
    public function generateAvatarUploadUrl(User $user, string $contentType): array
    {
        return $this->storage->generateAvatarUploadUrl(
            $user->uuid,
            $contentType,
            self::AVATAR_MIME_TYPES[$contentType],
        );
    }

    /**
     * Confirma o upload da foto: confere o arquivo de verdade (o PUT pré-assinado não limita
     * tipo nem tamanho), troca a foto e apaga a anterior.
     */
    public function setAvatar(User $user, string $path): User
    {
        $maxSize = (int) config('services.s3.avatar_max_size', 5242880);

        if (! array_key_exists($this->storage->mimeType($path), self::AVATAR_MIME_TYPES)) {
            $this->storage->delete($path);

            throw ProfileException::invalidAvatarFile();
        }

        if ($this->storage->size($path) > $maxSize) {
            $this->storage->delete($path);

            throw ProfileException::avatarTooLarge($maxSize);
        }

        $user->update([
            'avatar_path' => $this->storage->replaceFile($user->avatar_path, $path),
        ]);

        return $user->refresh();
    }

    public function removeAvatar(User $user): User
    {
        if ($user->avatar_path) {
            $this->storage->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return $user->refresh();
    }

    /**
     * Troca a senha e encerra as outras sessões; o token em uso continua válido.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);

        $current = $user->currentAccessToken();

        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($query) => $query->where('id', '!=', $current->id))
            ->delete();
    }
}
