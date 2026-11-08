<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\ListsService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Alta de lista: personal o del hogar activo.
 */
final class ListCreate extends NativeComponent
{
    public string $name = '';

    public string $scope = 'personal';

    public ?string $householdId = null;

    public ?string $householdName = null;

    public bool $busy = false;

    public ?string $error = null;

    public function mount(): void
    {
        try {
            $me = (new AuthService)->me();
            $this->householdId = is_string($me['active_household_id'] ?? null)
                ? $me['active_household_id']
                : null;

            if ($this->householdId !== null) {
                foreach ((new ListsService)->households() as $household) {
                    if (($household['id'] ?? null) === $this->householdId) {
                        $this->householdName = is_string($household['name'] ?? null)
                            ? $household['name']
                            : null;
                    }
                }
            }

            $this->scope = $this->householdId !== null ? 'household' : 'personal';
        } catch (HomesideException) {
            $this->householdId = null;
            $this->scope = 'personal';
        }
    }

    public function navTitle(): string
    {
        return 'Nueva lista';
    }

    public function chooseScope(string $scope): void
    {
        $this->scope = $scope;
        $this->error = null;
    }

    public function save(?string $submittedName = null): void
    {
        if ($this->busy) {
            return;
        }

        if (is_string($submittedName) && trim($submittedName) !== '') {
            $this->name = $submittedName;
        }

        $this->error = null;

        if (trim($this->name) === '') {
            $this->error = 'Poné un nombre para la lista.';

            return;
        }

        $householdId = $this->scope === 'household' ? $this->householdId : null;

        $this->busy = true;

        try {
            (new ListsService)->create($this->name, $householdId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->name = '';

        $this->back();
    }

    public function render(): View
    {
        return view('native.list-create');
    }
}
