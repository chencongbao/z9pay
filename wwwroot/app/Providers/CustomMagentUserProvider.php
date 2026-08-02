<?php


namespace App\Providers;

use App\Jobs\TelegramJob;
use App\Models\AgentUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class CustomMagentUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        return AgentUser::where('id', $identifier)->first();
    }

    public function retrieveByToken($identifier, $token)
    {
        $retrievedModel = AgentUser::where('id', $identifier)->first();
        if (!$retrievedModel) {
            return;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token)
            ? $retrievedModel : null;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    public function retrieveByCredentials(array $credentials)
    {
        $vo = AgentUser::where('username', $credentials['username'])->where('status', 1)->first();
        if ($vo) return $vo;
        return;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return !!Hash::check($credentials['password'], $user->getAuthPassword());
    }
}
