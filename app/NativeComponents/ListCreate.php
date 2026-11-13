<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\HouseholdsService;
use App\Services\Homeside\ListsService;
use App\Services\Settings\LocaleService;
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
        (new LocaleService)->apply();

        try {
            $households = app(HouseholdsService::class);

            $this->householdId = $households->activeId();
            $this->householdName = $households->name($this->householdId);

            $this->scope = $this->householdId !== null ? 'household' : 'personal';
        } catch (HomesideException) {
            $this->householdId = null;
            $this->scope = 'personal';
        }
    }

    public function navTitle(): string
    {
        return __('app.list_create.title');
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
            $this->error = __('app.list_create.name_required');

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
