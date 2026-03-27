<?php

declare(strict_types=1);

namespace Phlox\Forms\DropDownList;

use Nette\Database\Table\Selection;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;

/**
 * DropDownList form control for Nette Framework.
 *
 * Searchable select backed by a Nette\Database\Table\Selection.
 * The AJAX search URL is supplied explicitly – pass a presenter signal link,
 * a standalone API route, or any URL returning JSON [{value, label}, …].
 *
 * Two rendering modes
 * -------------------
 *  DropDownList (default)
 *    Read-only trigger button – clicking opens a panel with a filter input
 *    above the item list. Free text cannot be entered or submitted;
 *    the hidden value is always a valid ID or empty.
 *
 *  ComboBox  (call ->asComboBox())
 *    Editable <input> – user can type to search. Submits only valid IDs;
 *    the hidden value is cleared if the user clears the field.
 *
 * Usage
 * -----
 *   $form->addDropDownList('cityId', 'City', $presenter->link('searchCity!'))
 *        ->setSelection($db->table('cities'), 'id', 'name')
 *        ->setLimit(30)
 *        ->setPlaceholder('Select city…')
 *        ->setTheme(DropDownListInput::THEME_BOOTSTRAP);
 *
 *   // Custom theme – pass an array with only the keys you want to override:
 *   $form->addDropDownList('cityId', 'City', $url)
 *        ->setTheme([
 *            'trigger'     => 'my-select',
 *            'filterInput' => 'my-search',
 *            'item'        => 'my-option',
 *        ]);
 *
 * Expected AJAX response format (GET ?q=<query>):
 *   [{"value": 1, "label": "Prague"}, …]
 *
 * @see DropDownListExtension
 * @see DropDownListEndpoint
 */
class DropDownListInput extends BaseControl
{
    // ─── Theme constants ──────────────────────────────────────────────────────

    public const THEME_DEFAULT   = 'default';
    public const THEME_BOOTSTRAP = 'bootstrap';
    public const THEME_TAILWIND  = 'tailwind';

    // ─── Configuration ────────────────────────────────────────────────────────

    private Selection $selection;
    private string $valueColumn      = 'id';
    private string $labelColumn      = 'name';
    private int $limit               = 20;
    private ?string $placeholder     = null;
    private string $theme            = self::THEME_DEFAULT;
    private array $cssClasses        = [];

    /** When true, renders an editable <input> instead of a read-only trigger. */
    private bool $comboBoxMode = false;

    /** User-overridable UI text strings. Merged over resolveTexts() defaults. */
    private array $texts = [];

    /** Human-readable label resolved server-side for the current value. */
    private ?string $preloadedLabel = null;

    public function __construct(string $label = '', private string $searchUrl = '')
    {
        parent::__construct($label);
        $this->setOption('type', 'dropdownlist');
    }

    // ─── Fluent configuration API ─────────────────────────────────────────────

    public function setSearchUrl(string $url): static
    {
        $this->searchUrl = $url;
        return $this;
    }

    /**
     * @param Selection $selection   Any Selection (may include wheres / orders).
     * @param string    $valueColumn Column used as the submitted value (typically PK).
     * @param string    $labelColumn Column shown to the user.
     */
    public function setSelection(
        Selection $selection,
        string $valueColumn = 'id',
        string $labelColumn = 'name',
    ): static {
        $this->selection   = $selection;
        $this->valueColumn = $valueColumn;
        $this->labelColumn = $labelColumn;
        return $this;
    }

    protected function getSelection(): Selection
    {
        return $this->selection;
    }

    /** Maximum items returned per AJAX call (default: 20). */
    public function setLimit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /** Placeholder shown in the trigger / input when nothing is selected. */
    public function setPlaceholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Configure the UI theme.
     *
     * Pass a THEME_* constant for a built-in preset, or an associative array
     * to define CSS classes for each part of the widget. Any omitted key falls
     * back to the default value – you only need to specify what you override.
     *
     * CSS map keys:
     *   wrapper       — outer <div>
     *   trigger       — read-only trigger <button> (DropDownList mode)
     *   dropdown      — dropdown panel <div>
     *   filterRow     — wrapper <div> around the filter input
     *   filterInput   — filter <input type="text">
     *   item          — each <li> option
     *   itemActive    — <li> when keyboard-highlighted
     *   itemSelected  — <li> matching the current value
     *   noResults     — <li> shown when search returns nothing
     *   mark          — <mark> wrapping matched substring
     *   input         — editable <input> (ComboBox mode only)
     *
     * @param string|array<string,string> $theme
     */
    public function setTheme(string|array $theme): static
    {
        if (\is_array($theme)) {
            $this->theme      = self::THEME_DEFAULT;
            $this->cssClasses = $theme;
        } else {
            $this->theme      = $theme;
            $this->cssClasses = [];
        }
        return $this;
    }

    /** Switch to ComboBox (editable) mode. Only valid IDs are submitted. */
    public function asComboBox(bool $mode = true): static
    {
        $this->comboBoxMode = $mode;
        return $this;
    }

    /**
     * Override UI text strings – useful for localisation or custom wording.
     *
     * Pass only the keys you want to change; omitted keys keep their defaults.
     *
     * Available keys and their defaults:
     *   noResults         — 'Žádné výsledky'   shown when AJAX returns an empty list
     *   filterPlaceholder — 'Vyhledat…'            placeholder inside the filter input
     *
     * @param array<string,string> $texts
     */
    public function setTexts(array $texts): static
    {
        $this->texts = array_merge($this->texts, $texts);
        return $this;
    }

    // ─── Value handling ───────────────────────────────────────────────────────

    /**
     * Set the value and pre-load the corresponding label from DB server-side.
     * No AJAX round-trip needed even for record deep in the dataset.
     */
    public function setValue($value): static
    {
        parent::setValue($value);

        if ($value !== null && $value !== '' && isset($this->selection)) {
            $row = (clone $this->selection)
                ->where($this->valueColumn, $value)
                ->fetch();

            $this->preloadedLabel = $row !== null
                ? (string) $row[$this->labelColumn]
                : null;
        } else {
            $this->preloadedLabel = null;
        }

        return $this;
    }

    /** Returns the selected ID, or null if nothing is selected. */
    public function getValue(): mixed
    {
        return ($this->value === '' || $this->value === null) ? null : $this->value;
    }

    // ─── Server-side data fetching ────────────────────────────────────────────

    /**
     * Fetch filtered records – call this in your signal / API action handler.
     *
     *   public function handleSearchCity(string $q = ''): void
     *   {
     *       $this->sendJson($this['form']['cityId']->fetchData($q));
     *   }
     *
     * Override in a subclass for custom filtering (joins, FTS, multi-column, …).
     *
     * @return list<array{value: mixed, label: string}>
     */
    public function fetchData(string $query = ''): array
    {
        $sel = clone $this->selection;

        if ($query !== '') {
            $sel->where("{$this->labelColumn} LIKE ?", '%' . $query . '%');
        }

        $results = [];
        foreach ($sel->limit($this->limit)->fetchAll() as $row) {
            $results[] = [
                'value' => $row[$this->valueColumn],
                'label' => (string) $row[$this->labelColumn],
            ];
        }

        return $results;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /**
     * Override getHtmlId() so Nette's auto-generated <label for="…"> points
     * at the visible trigger/input rather than the hidden value input.
     */
    public function getHtmlId(): string
    {
        return parent::getHtmlId() . '-search';
    }

    public function getControl(): Html
    {
        return $this->comboBoxMode
            ? $this->renderComboBox()
            : $this->renderDropDownList();
    }

    // ─── Private renderers ────────────────────────────────────────────────────

    /**
     * DropDownList (default): read-only trigger button + panel with filter + list.
     *
     * The trigger is a <button type="button"> so that:
     *   - <label for="id"> natively focuses it (no JS needed)
     *   - Space / Enter natively fire a click event
     *   - Tab order works without explicit tabindex
     *
     * HTML structure:
     *
     *   <div class="dropdownlist-wrapper">
     *     <input type="hidden" name="…" value="42">
     *
     *     <button type="button" id="…-search" class="dropdownlist-trigger"
     *             data-dropdownlist role="combobox" aria-haspopup="listbox"
     *             aria-expanded="false">
     *       <span class="dropdownlist-trigger-text">Prague</span>
     *       <span class="dropdownlist-trigger-arrow" aria-hidden="true"></span>
     *     </button>
     *
     *     <div class="dropdownlist-dropdown" data-dropdownlist-panel style="display:none">
     *       <div class="dropdownlist-filter">
     *         <input type="text" data-dropdownlist-filter placeholder="Filter">
     *       </div>
     *       <ul role="listbox" class="dropdownlist-list"></ul>
     *     </div>
     *   </div>
     */
    private function renderDropDownList(): Html
    {
        $cls = $this->resolveThemeClasses();
        $txt = $this->resolveTexts();

        $currentValue = $this->getValue();
        $currentLabel = $this->preloadedLabel ?? '';
        $hasValue     = ($currentLabel !== '');

        // --- Hidden value input ---
        $hidden = Html::el('input')
            ->type('hidden')
            ->name($this->getHtmlName())
            ->id(parent::getHtmlId() . '-value')
            ->value($currentValue ?? '');

        if ($this->isDisabled()) {
            $hidden->disabled(true);
        }

        // --- Trigger button ---
        // Using <button> makes <label for="id"> work natively without JS hacks.
        // Space/Enter natively fire click, so the JS only needs to handle ArrowDown.
        $trigger = Html::el('button')
            ->type('button')
            ->id($this->getHtmlId())
            ->setAttribute('data-dropdownlist', '')
            ->setAttribute('data-ajax-url', $this->searchUrl)
            ->setAttribute('data-limit', (string) $this->limit)
            ->setAttribute('data-placeholder', $this->placeholder ?? '')
            ->setAttribute('role', 'combobox')
            ->setAttribute('aria-haspopup', 'listbox')
            ->setAttribute('aria-expanded', 'false');

        if ($cls['trigger'] !== '') {
            $trigger->class($cls['trigger']);
        }

        // Pass theme classes to JS via data-cls-* attributes
        foreach ($this->buildDataAttributes($cls) as $attr => $value) {
            $trigger->setAttribute($attr, $value);
        }

        foreach ($this->buildTextAttributes($txt) as $attr => $value) {
            $trigger->setAttribute($attr, $value);
        }

        if ($this->isDisabled()) {
            $trigger->disabled(true);
            $trigger->setAttribute('aria-disabled', 'true');
        }

        // Label / placeholder span
        $labelSpan = Html::el('span')->class('dropdownlist-trigger-text');
        if ($hasValue) {
            $labelSpan->setText($currentLabel);
        } else {
            $labelSpan->addClass('dropdownlist-trigger-placeholder');
            $labelSpan->setText($this->placeholder ?? '');
        }

        // Arrow chevron (hidden by CSS when theme provides its own, e.g. Bootstrap form-select)
        $arrowSpan = Html::el('span')
            ->class('dropdownlist-trigger-arrow')
            ->setAttribute('aria-hidden', 'true');

        $trigger->addHtml($labelSpan);
        $trigger->addHtml($arrowSpan);

        // --- Dropdown panel ---
        // data-dropdownlist-panel is the stable JS hook; theme classes may vary.
        $panel = Html::el('div')
            ->setAttribute('data-dropdownlist-panel', '')
            ->setAttribute('style', 'display:none');

        if ($cls['dropdown'] !== '') {
            $panel->class($cls['dropdown']);
        }

        // Filter row
        $filterRow = Html::el('div');
        if ($cls['filterRow'] !== '') {
            $filterRow->class($cls['filterRow']);
        }

        $filterInput = Html::el('input')
            ->type('text')
            ->setAttribute('data-dropdownlist-filter', '')
            ->setAttribute('autocomplete', 'off')
            ->setAttribute('placeholder', $txt['filterPlaceholder']);

        if ($cls['filterInput'] !== '') {
            $filterInput->class($cls['filterInput']);
        }

        $filterRow->addHtml($filterInput);

        // Item list (JS populates)
        $list = Html::el('ul')
            ->setAttribute('role', 'listbox')
            ->class('dropdownlist-list');

        $panel->addHtml($filterRow);
        $panel->addHtml($list);

        // --- Wrapper ---
        $wrapper = Html::el('div');
        if ($cls['wrapper'] !== '') {
            $wrapper->class($cls['wrapper']);
        }

        $wrapper->addHtml($hidden);
        $wrapper->addHtml($trigger);
        $wrapper->addHtml($panel);

        return $wrapper;
    }

    /**
     * ComboBox mode: editable text input + plain dropdown listbox.
     *
     * HTML structure:
     *
     *   <div class="dropdownlist-wrapper">
     *     <input type="hidden" name="…" value="42">
     *     <input type="text" id="…-search" data-dropdownlist data-mode="combobox" …>
     *     <ul role="listbox" class="dropdownlist-dropdown dropdownlist-combobox-dropdown"
     *         data-dropdownlist-panel style="display:none"></ul>
     *   </div>
     */
    private function renderComboBox(): Html
    {
        $cls = $this->resolveThemeClasses();
        $txt = $this->resolveTexts();

        $currentValue = $this->getValue();
        $currentLabel = $this->preloadedLabel ?? '';

        // --- Hidden value input ---
        $hidden = Html::el('input')
            ->type('hidden')
            ->name($this->getHtmlName())
            ->id(parent::getHtmlId() . '-value')
            ->value($currentValue ?? '');

        // --- Visible text input ---
        $text = Html::el('input')
            ->type('text')
            ->id($this->getHtmlId())
            ->value($currentLabel)
            ->setAttribute('autocomplete', 'off')
            ->setAttribute('data-dropdownlist', '')
            ->setAttribute('data-mode', 'combobox')
            ->setAttribute('data-ajax-url', $this->searchUrl)
            ->setAttribute('data-limit', (string) $this->limit)
            ->setAttribute('aria-autocomplete', 'list')
            ->setAttribute('aria-haspopup', 'listbox')
            ->setAttribute('aria-expanded', 'false');

        if ($cls['input'] !== '') {
            $text->class($cls['input']);
        }

        if ($this->placeholder !== null) {
            $text->placeholder($this->placeholder);
        }

        foreach ($this->buildDataAttributes($cls) as $attr => $value) {
            $text->setAttribute($attr, $value);
        }

        foreach ($this->buildTextAttributes($txt) as $attr => $value) {
            $text->setAttribute($attr, $value);
        }

        if ($this->isDisabled()) {
            $text->disabled(true);
            $hidden->disabled(true);
        }

        // --- Dropdown (plain listbox, no filter row) ---
        $dropdownClass = trim(($cls['dropdown'] !== '' ? $cls['dropdown'] : '') . ' dropdownlist-combobox-dropdown');

        $dropdown = Html::el('ul')
            ->setAttribute('role', 'listbox')
            ->setAttribute('data-dropdownlist-panel', '')
            ->setAttribute('style', 'display:none');

        if (trim($dropdownClass) !== '') {
            $dropdown->class(trim($dropdownClass));
        }

        // --- Wrapper ---
        $wrapper = Html::el('div');
        if ($cls['wrapper'] !== '') {
            $wrapper->class($cls['wrapper']);
        }

        $wrapper->addHtml($hidden);
        $wrapper->addHtml($text);
        $wrapper->addHtml($dropdown);

        return $wrapper;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Resolve text strings for the current instance.
     * User overrides from setTexts() are merged over the defaults.
     *
     * @return array<string,string>
     */
    private function resolveTexts(): array
    {
        $defaults = [
            'noResults'         => 'Žádné výsledky',
            'filterPlaceholder' => 'Vyhledat…',
        ];

        return array_merge($defaults, $this->texts);
    }

    /**
     * Resolve CSS classes for the current theme.
     *
     * Works like UploadControl::resolveThemeClasses():
     *   – built-in themes define a full map via match()
     *   – custom array themes are merged on top of the defaults
     *
     * @return array<string,string>
     */
    private function resolveThemeClasses(): array
    {
        $defaults = [
            'wrapper'      => 'dropdownlist-wrapper',
            'trigger'      => 'dropdownlist-trigger',
            'dropdown'     => 'dropdownlist-dropdown',
            'filterRow'    => 'dropdownlist-filter',
            'filterInput'  => 'dropdownlist-filter-input',
            'item'         => '',
            'itemActive'   => 'dropdownlist-active',
            'itemSelected' => 'dropdownlist-selected',
            'noResults'    => 'dropdownlist-no-results',
            'mark'         => '',
            'input'        => 'dropdownlist-input',
        ];

        $map = match ($this->theme) {
            self::THEME_BOOTSTRAP => [
                'wrapper'      => 'dropdownlist-wrapper position-relative',
                // form-select provides its own chevron via background-image;
                // dropdownlist-bs-trigger suppresses our custom arrow via CSS.
                'trigger'      => 'form-select text-start dropdownlist-bs-trigger',
                'dropdown'     => 'dropdown-menu w-100 py-0',
                'filterRow'    => 'p-3',
                'filterInput'  => 'form-control form-control-sm',
                'item'         => 'dropdown-item',
                'itemActive'   => 'active',
                'itemSelected' => 'fw-semibold',
                'noResults'    => 'dropdown-item disabled text-muted fst-italic',
                'mark'         => 'fw-bold bg-transparent p-0',
                'input'        => 'form-control',
            ],
            self::THEME_TAILWIND => [
                'wrapper'      => 'relative',
                'trigger'      => implode(' ', [
                    'flex items-center justify-between w-full rounded-md border-0 py-1.5 pl-3 pr-3',
                    'text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 cursor-pointer select-none',
                    'focus:outline-none focus:ring-2 focus:ring-indigo-600',
                    'dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600',
                    'dark:focus:ring-indigo-500',
                    'sm:text-sm sm:leading-6',
                ]),
                'dropdown'     => implode(' ', [
                    'absolute z-50 mt-1 w-full rounded-md',
                    'bg-white text-base shadow-lg ring-1 ring-black ring-opacity-5',
                    'dark:bg-gray-800 dark:ring-gray-700',
                ]),
                'filterRow'    => 'p-2 border-b border-gray-200 dark:border-gray-700',
                'filterInput'  => implode(' ', [
                    'block w-full rounded border border-gray-300 bg-white',
                    'py-1.5 pl-7 pr-3 text-sm text-gray-900',
                    'placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500',
                    'dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder:text-gray-500',
                ]),
                'item'         => implode(' ', [
                    'relative cursor-pointer select-none px-4 py-2 text-sm',
                    'text-gray-700 hover:bg-gray-100',
                    'dark:text-gray-200 dark:hover:bg-gray-700',
                ]),
                'itemActive'   => 'bg-indigo-50 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-100',
                'itemSelected' => 'font-semibold',
                'noResults'    => 'px-4 py-2 text-sm italic text-gray-400 dark:text-gray-500',
                'mark'         => 'bg-transparent font-bold text-indigo-600 dark:text-indigo-400',
                'input'        => implode(' ', [
                    'block w-full rounded-md border-0 py-1.5 pl-3 pr-8',
                    'text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300',
                    'placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600',
                    'dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:placeholder:text-gray-500',
                    'dark:focus:ring-indigo-500 sm:text-sm sm:leading-6',
                ]),
            ],
            default => \array_merge($defaults, $this->cssClasses),
        };

        return \array_merge($defaults, $map);
    }

    /**
     * Build data-cls-* attributes for the JS widget from the resolved class map.
     *
     * @param array<string,string> $cls
     * @return array<string,string>
     */
    private function buildDataAttributes(array $cls): array
    {
        return [
            'data-cls-item'          => $cls['item'],
            'data-cls-item-active'   => $cls['itemActive'],
            'data-cls-item-selected' => $cls['itemSelected'],
            'data-cls-no-results'    => $cls['noResults'],
            'data-cls-mark'          => $cls['mark'],
        ];
    }

    /**
     * Build data-txt-* attributes for the JS widget from the resolved text map.
     *
     * @param array<string,string> $txt
     * @return array<string,string>
     */
    private function buildTextAttributes(array $txt): array
    {
        return [
            'data-txt-no-results' => $txt['noResults'],
        ];
    }
}
