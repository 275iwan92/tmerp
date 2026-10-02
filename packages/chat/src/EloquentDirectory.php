<?php

namespace Chat;

use Chat\Contracts\Directory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

// Direktori bawaan: semua baris model user aplikasi. Aplikasi yang punya aturan sendiri (user aktif saja, jabatan, foto)
// mengikat implementasi Directory miliknya di container.
class EloquentDirectory implements Directory
{
    public function people(Authenticatable $me): array
    {
        $model = $this->model();

        return $model->newQuery()->whereKeyNot($me->getAuthIdentifier())->orderBy(config('chat.user_name'))->get()
            ->map(fn (Model $u) => $this->row($u))->all();
    }

    public function person(int $id): ?array
    {
        $u = $this->model()->newQuery()->find($id);

        return $u ? $this->row($u) : null;
    }

    public function key(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }

    public function name(Authenticatable $user): string
    {
        return (string) $user->{config('chat.user_name')};
    }

    private function model(): Model
    {
        $class = config('chat.user_model') ?? config('auth.providers.'.config('auth.guards.'.(config('chat.guard') ?? config('auth.defaults.guard')).'.provider').'.model');

        return new $class;
    }

    private function row(Model $u): array
    {
        return ['id' => (int) $u->getKey(), 'key' => (string) $u->getKey(), 'name' => (string) $u->{config('chat.user_name')}, 'role' => '', 'photo' => null];
    }
}
