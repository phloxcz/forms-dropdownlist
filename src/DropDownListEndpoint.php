<?php

declare(strict_types=1);

namespace Phlox\Forms\DropDownList;

use Nette\Application\UI\Control;

/**
 * AJAX endpoint component for all DropDownList controls on a presenter.
 *
 * The presenter only needs to declare one component factory – the endpoint
 * then handles search signals for every DropDownListInput on every form
 * on that presenter. No trait, no per-field wiring required.
 *
 * Presenter setup
 * ---------------
 *   protected function createComponentDropdownlist(): DropDownListEndpoint
 *   {
 *       return new DropDownListEndpoint;
 *   }
 *
 * Or wire it automatically via DropDownListExtension in config.neon:
 *   extensions:
 *       dropdownlist: Phlox\Forms\DropDownList\DropDownListExtension
 *
 * Each DropDownListInput registers itself into the endpoint automatically
 * inside DropDownListInput::attached() – the presenter stays unaware.
 *
 * AJAX URL pattern:  ?do=dropdownlist-search&field=<controlName>&q=<query>
 */
class DropDownListEndpoint extends Control
{
    /** Name under which the endpoint must be registered as a component. */
    public const COMPONENT_NAME = 'dropdownlist';

    /** @var array<string, DropDownListInput> fieldName → control */
    private array $controls = [];

    /**
     * Called by DropDownListInput::attached() – the control registers itself here.
     */
    public function register(DropDownListInput $control): void
    {
        $this->controls[$control->getName()] = $control;
    }

    /**
     * AJAX signal.
     *
     * Route:  ?do=dropdownlist-search&field=cityId&q=pra
     *
     * @param string $field  Name of the DropDownListInput control (e.g. "cityId").
     * @param string $q      Search query typed by the user.
     */
    public function handleSearch(string $field, string $q = ''): void
    {
        $control = $this->controls[$field] ?? null;

        if ($control === null) {
            $this->presenter->sendJson(['error' => "DropDownList field '{$field}' not found."]);
        }

        $this->presenter->sendJson($control->fetchData($q));
    }
}
