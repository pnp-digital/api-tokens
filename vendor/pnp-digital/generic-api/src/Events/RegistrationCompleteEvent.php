<?php

namespace Pipeandpiper\API\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * @property Request $request
 * @property User $newUser
 * @property array<string, mixed> $extraData
 */
class RegistrationCompleteEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<string, mixed> $extraData
     */
    public function __construct(protected Request $request, protected User $newUser, protected array $extraData = [])
    {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getNewUser(): User
    {
        return $this->newUser;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraData(): array
    {
        return $this->extraData;
    }


}