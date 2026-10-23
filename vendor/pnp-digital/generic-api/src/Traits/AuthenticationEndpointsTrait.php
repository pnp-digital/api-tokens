<?php

namespace Pipeandpiper\API\Traits;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Events\RegistrationCompleteEvent;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Transformers\NullTransformer;
use Pipeandpiper\Mail\Traits\MailTemplateTrait;
use Symfony\Component\HttpFoundation\InputBag;
use Throwable;

trait AuthenticationEndpointsTrait
{
    use MailTemplateTrait;

    /**
     * Attempt to register an API user, returning a bearer token
     *
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @return Item token and user id
     * @throws ValidationException if login credentials are invalid
     */
    public function register(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        if (env("API_USER_REGISTRATION_DISABLED", 0)) {
            throw ValidationException::withMessages(["email" => "User registration is not enabled"]);
        }
        $model = 'App\\Models\\User';
        $originalRequest = clone($request);
        /** @var array<string, mixed> $content */
        $content = $request->except(['is_admin', 'id', 'is_super', 'is_enabled', 'device_name', 'meta']);
        $pb = new InputBag($content);
        $pb->add(['is_enabled'=>true]);
        $request->request = $pb;
        $request->setJson($pb);
        /** @var object $existingUser */
        $existingUser = User::query()->where("email", "=", $request->input("email"));
        /** @phpstan-ignore-next-line */
        $existingUser = $existingUser->withTrashed()->first();
        if ($existingUser) {
            throw ValidationException::withMessages(["email" => "User already exists"]);
        }
        $this->withTransactions = false;
        $token = null;
        DB::transaction(function() use(&$token, $request, $serializer, $manager, $model, $originalRequest) {
            $this->post($request, $serializer, $manager, "basic", $model);
            if (!Auth::attempt($request->only('email', 'password'))) {
                throw ValidationException::withMessages([
                    'email' => ['Could not log in new user'],
                ]);
            }
            /** @var User $user */
            $user = $request->user();
            /** @var string $deviceName */
            $deviceName = $request->device_name ?? 'Unknown';
            $token = defined('PHPUNIT_COMPOSER_INSTALL') ? (object)['plainTextToken' => 'test-token'] : $user->createToken($deviceName);
            RegistrationCompleteEvent::dispatch($originalRequest, $user);
        });
        $this->withTransactions = true;

        return new Item($token, function ($token) use (&$request) {
            /** @var object{plainTextToken: string} $token */
            /** @var User $requestUser */
            $requestUser = $request->user();
            /** @var User $user */
            $user = $this->addAutoJoins(User::class, $requestUser, User::query()->where("id", "=", Auth::id()))->first();
            /** @var array<int, array<string, mixed>> $arr */
            $arr = $user->toArray();
            if ($user instanceof ApiFilterInterface) {
                $arr = $user->apiPostProcess($arr);
            }
            return [
                array_merge($arr, ['token' => $token->plainTextToken, 'registration_status' => true])
            ];
        });
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @return Item
     * @throws ValidationException
     */
    public function resetpassword(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        if (env("API_USER_PASSWORD_RESET_DISABLED", 0)) {
            throw ValidationException::withMessages(["email" => "User registration is not enabled"]);
        }
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return new Item(['reset_status' => $status], new NullTransformer());
    }

    /**
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @return Item
     * @throws ValidationException
     */
    public function changepassword(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        /** @var string $oldPassword */
        $oldPassword = $request->input("old_password");
        /** @var string $newPassword */
        $newPassword = $request->input("new_password");
        if (empty($oldPassword)) {
            throw ValidationException::withMessages(["old_password" => "Missing current password"]);
        }
        if (empty($newPassword)) {
            throw ValidationException::withMessages(["new_password" => "Missing new password"]);
        }
        /** @var User $user */
        $user = $request->user();
        if (!Hash::check($oldPassword, $user->password)) {
            throw ValidationException::withMessages(["old_password" => "Current password is incorrect"]);
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        return new Item(['status' => true], new NullTransformer());
    }


    /**
     * Attempt to log in an API user, returning a bearer token
     *
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @return Item token and user id
     * @throws ValidationException if login credentials are invalid
     * @noinspection PhpUnusedParameterInspection
     */
    public function login(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        /**
         * NOTE: This check bypasses fortify and any special checks there, which is why non-admins
         * can use the API as intended. This is because the Auth::attempt() directly calls the guard login
         * the same way fortify does, bypassing fortify and this check.
         * See: https://github.com/laravel/fortify/blob/1.x/src/Actions/AttemptToAuthenticate.php
         *
         * https://discord.gg/laravel
         * https://discord.com/channels/297040613688475649/751447306901127349/1136589430090641508
         */
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Either the username or password you are trying to log in with are incorrect'],
            ]);
        }

        /** @var User|null $nUser */
        $nUser = $request->user();
        $fullUser = $nUser?->makeVisible(['two_factor_secret', 'two_factor_recovery_codes']);

        /** @var User $fullUser */

        /**
         * If the two factor recovery codes field just contains "*", and nothing else, this is a boolean indicator
         * for enabling simple email 2fa. Simple email 2fa just emails the user a code, with no recovery codes supported.
         */
        if ($fullUser->two_factor_recovery_codes === "*") {
            $lastMod = new Carbon($fullUser->updated_at);
            if ((empty($fullUser->two_factor_secret) || !empty($request->input("resend")) || abs($lastMod->diffInMinutes(now())) > 15) || (!empty($fullUser->two_factor_secret) && empty($request->input("two_factor_code")))) {
                /* Two factor enabled for user but no code, code expired, or requested resend: send email */
                try {
                    $fullUser->two_factor_secret = (string)rand(100000,999999);
                    $fullUser->save();
                    $this->sendEmail($fullUser->email, null, "two_factor", [
                        'user_name' => $fullUser->email,
                        'token' => $fullUser->two_factor_secret
                    ]);
                } catch (Exception $e) {
                    auth()->logout();
                    throw ValidationException::withMessages([
                        'email' => ['Two factor authentication is enabled for this user; email could not be sent. Please try again later: ' . $e->getMessage()],
                    ]);
                }
                auth()->logout();
                throw ValidationException::withMessages([
                    'email' => ['Two factor authentication is enabled for this user; email has been sent, retry this call with 2FA code'],
                ]);
            } elseif (!empty($fullUser->two_factor_secret) && !empty($request->input("two_factor_code"))) {
                /* Two factor enabled, code has been set, email sent, two-factor code enclosed, check it matches */
                if ($request->input("two_factor_code") !== $fullUser->two_factor_secret) {
                    auth()->logout();
                    throw ValidationException::withMessages([
                        'email' => ['Two factor authentication code is incorrect'],
                    ]);
                }
                /* Clear token */
                /** @var User $user */
                $user = request()->user();
                $user->two_factor_secret = "";
                $user->save();
            }
        }

        /** @var string $deviceName */
        $deviceName = $request->device_name ?? 'Unknown';
        $token = defined('PHPUNIT_COMPOSER_INSTALL') ? (object)['plainTextToken' => 'test-token'] : $nUser?->createToken($deviceName);
        $serializer->setPagination(1, 50, 'id', 1);

        return new Item($token, function ($token) use ($nUser) {
            /** @var object{plainTextToken: string} $token */
            /** @var User $nUser */
            /** @var User $user */
            $user = $this->addAutoJoins(User::class, $nUser, User::query()->where("id", "=", Auth::id()))->first();
            /** @var array<int, array<string, mixed>> $arr */
            $arr = $user->toArray();
            if ($user instanceof ApiFilterInterface) {
                $arr = $user->apiPostProcess($arr);
            }
            return [
                array_merge($arr, ['token' => $token->plainTextToken])
            ];
        });
    }

    /**
     * Log out current API user
     *
     * @param Request $request
     * @param PNPJSONAPISerializer $serializer
     * @param Manager $manager
     * @return Item
     * @noinspection PhpUnusedParameterInspection
     */
    function logout(Request $request, PNPJSONAPISerializer $serializer, Manager $manager): Item
    {
        Auth::logout();
        $serializer->setPagination(1, 50, 'id', 1);
        return new Item([], new NullTransformer());
    }


}
