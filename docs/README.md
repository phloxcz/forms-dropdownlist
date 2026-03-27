# phloxcz/forms-dropdownlist – Full documentation

← [Back to project root](../README.md)

---


Searchable **DropDownList** form control for [Nette Framework](https://nette.org) with AJAX-powered `Nette\Database\Table\Selection` datasource.

Items load on demand – no full page reload, no hardcoded `<option>` list in templates. Behaviour mirrors **Kendo UI DropDownList**: the trigger is read-only, the user picks from a list; free text cannot be submitted.

---

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Rendering modes](#rendering-modes)
  - [DropDownList (default)](#dropdownlist-default)
  - [ComboBox mode](#combobox-mode)
- [API reference](#api-reference)
  - [DropDownListInput](#dropdownlistinput)
  - [DropDownListExtension](#dropdownlistextension)
  - [DropDownListEndpoint](#dropdownlistendpoint)
- [AJAX endpoint](#ajax-endpoint)
- [Themes](#themes)
  - [Default theme](#default-theme)
  - [Bootstrap 5](#bootstrap-5)
  - [Tailwind CSS 3](#tailwind-css-3)
  - [Custom theme](#custom-theme)
- [Keyboard navigation](#keyboard-navigation)
- [Pre-filling values (edit pages)](#pre-filling-values-edit-pages)
- [Advanced: custom filtering](#advanced-custom-filtering)
- [Dark mode](#dark-mode)
- [Naja / AJAX snippets](#naja--ajax-snippets)
- [License](#license)

---

## Features

- **Read-only trigger** by default – only valid IDs can be submitted, no arbitrary text
- **Filter input inside the dropdown** for fast item search
- Optional **ComboBox mode** (`->asComboBox()`) for editable free-text search
- **Server-side label pre-loading** – edit pages show the current value without an extra AJAX call
- **Vanilla JS**, zero dependencies (works alongside jQuery, Alpine, etc.)
- **Bootstrap 5**, **Tailwind CSS 3**, and fully custom theme support
- **Dark mode** via `prefers-color-scheme` (default theme) or framework utilities (Bootstrap/Tailwind)
- Keyboard navigation: ↑ ↓ Enter Tab Escape
- [Naja](https://naja.js.org/) / AJAX snippet support via `MutationObserver`
- PHPStan level 6 clean

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.1 |
| nette/forms | ^3.1 |
| nette/database | ^3.1 |
| nette/application | ^3.1 |
| nette/di | ^3.1 |
| nette/utils | ^4.0 |

---

## Installation

```bash
composer require phloxcz/forms-dropdownlist
```

Include the assets in your base layout:

```html
<link  rel="stylesheet" href="{$basePath}/vendor/phloxcz/forms-dropdownlist/assets/dropdownlist.css">
<script src="{$basePath}/vendor/phloxcz/forms-dropdownlist/assets/dropdownlist.js" defer></script>
```

> **Tip:** Copy or symlink `assets/` into your public directory, or bundle them through your asset pipeline (Webpack, Vite, …).

---

## Quick start

### 1. Register the DI extension

**`config/common.neon`**

```neon
extensions:
    dropdownlist: Phlox\Forms\DropDownList\DropDownListExtension
```

Without DI (e.g. in `Bootstrap.php`):

```php
\Phlox\Forms\DropDownList\DropDownListExtension::register();
```

### 2. Add the control to your form

```php
protected function createComponentCityForm(): Form
{
    $form = new Form;

    $form->addDropDownList('cityId', 'City', $this->link('searchCity!'))
         ->setSelection($this->database->table('cities'), 'id', 'name')
         ->setLimit(30)
         ->setPlaceholder('Select city…')
         ->setTheme(DropDownListInput::THEME_BOOTSTRAP);

    $form->addSubmit('send', 'Save');
    $form->onSuccess[] = $this->cityFormSuccess(...);

    return $form;
}
```

### 3. Handle the AJAX signal

```php
public function handleSearchCity(string $q = ''): void
{
    $this->sendJson($this['cityForm']['cityId']->fetchData($q));
}
```

Response format:

```json
[
  { "value": 1, "label": "Prague" },
  { "value": 2, "label": "Brno" },
  { "value": 3, "label": "Ostrava" }
]
```

### 4. Read the submitted value

```php
public function cityFormSuccess(Form $form, \stdClass $data): void
{
    // $data->cityId is the selected integer ID, or null if nothing was picked
    $this->cityRepository->save((int) $data->cityId);
}
```

---

## Rendering modes

### DropDownList (default)

The default mode renders a **read-only trigger button**. Clicking it opens a dropdown panel containing:

1. A **filter input** (search-as-you-type, debounced AJAX)
2. A **scrollable item list**

The user can only submit a value that exists in the datasource. Free text is never submitted.

```
┌─────────────────────────────────┐
│ Select city…                  ▾ │  ← read-only trigger
└─────────────────────────────────┘
┌─────────────────────────────────┐
│ 🔍 Filter                       │  ← filter input (focused on open)
├─────────────────────────────────┤
│ Prague                          │
│ Brno                            │  ← scrollable item list
│ Ostrava                         │
└─────────────────────────────────┘
```

```php
$form->addDropDownList('cityId', 'City', $this->link('searchCity!'))
     ->setSelection($this->db->table('cities'), 'id', 'name')
     ->setPlaceholder('Select city…');
// No extra call needed – DropDownList is the default.
```

### ComboBox mode

Switches to an **editable text input**. The user types directly into the field; the dropdown list appears and filters in real time. Only picking a concrete item sets the hidden value – typing without selecting leaves the value empty.

```php
$form->addDropDownList('cityId', 'City', $this->link('searchCity!'))
     ->setSelection($this->db->table('cities'), 'id', 'name')
     ->asComboBox()
     ->setPlaceholder('Type to search…');
```

---

## API reference

### DropDownListInput

| Method | Returns | Description |
|---|---|---|
| `setSearchUrl(string $url)` | `static` | Override the AJAX URL set in the constructor |
| `setSelection(Selection $sel, string $valueCol, string $labelCol)` | `static` | Nette Database `Selection` used for server-side queries and label pre-loading |
| `setLimit(int $limit)` | `static` | Maximum items returned per AJAX call (default: `20`) |
| `setPlaceholder(string $text)` | `static` | Placeholder shown in the trigger / input when nothing is selected |
| `setTexts(array $texts)` | `static` | Override any UI text string (see table below) |
| `setTheme(string\|array $theme)` | `static` | Apply a theme: pass a `THEME_*` constant or a custom class-map array |
| `asComboBox(bool $mode = true)` | `static` | Switch to editable ComboBox mode |
| `setValue(mixed $value)` | `static` | Set the selected value; resolves the label from DB server-side |
| `getValue()` | `mixed` | Returns the selected ID, or `null` |
| `fetchData(string $query = '')` | `array` | Fetch items matching `$query` from the Selection – call this in your AJAX handler |


### Text strings

All user-visible strings can be overridden via `setTexts()`. Pass only the keys you want to change.

```php
$form->addDropDownList('cityId', 'City', $url)
     ->setTexts([
         'noResults'         => 'No cities found',
         'filterPlaceholder' => 'Search…',
     ]);
```

| Key | Default | Description |
|---|---|---|
| `noResults` | `'Žádné výsledky'` | Text shown when the AJAX search returns an empty list |
| `filterPlaceholder` | `'Vyhledat…'` | Placeholder inside the filter input (DropDownList mode) |


### Theme constants

Defined on `DropDownListInput`:

| Constant | Description |
|---|---|
| `THEME_DEFAULT` | Framework-agnostic default. Dark mode via `prefers-color-scheme`. |
| `THEME_BOOTSTRAP` | Bootstrap 5. Dark mode via `[data-bs-theme="dark"]` on `<html>`. |
| `THEME_TAILWIND` | Tailwind CSS 3. Dark mode via `dark:` class variants. |

`setTheme(array $classes)` map keys:

| Key | Applied to | Default class |
|---|---|---|
| `wrapper` | Outer `<div>` | `dropdownlist-wrapper` |
| `trigger` | Read-only trigger `<div>` | `dropdownlist-trigger` |
| `dropdown` | Dropdown panel `<div>` | `dropdownlist-dropdown` |
| `filterRow` | Filter row `<div>` | `dropdownlist-filter` |
| `filterInput` | Filter `<input>` | `dropdownlist-filter-input` |
| `item` | Each `<li>` option | _(empty)_ |
| `itemActive` | Keyboard-highlighted `<li>` | `dropdownlist-active` |
| `itemSelected` | `<li>` matching current value | `dropdownlist-selected` |
| `noResults` | "No results" `<li>` | `dropdownlist-no-results` |
| `mark` | `<mark>` around matched text | _(empty)_ |
| `input` | Editable `<input>` (ComboBox mode) | `dropdownlist-input` |

### DropDownListExtension

Nette DI extension that registers `addDropDownList()` on every `Nette\Forms\Container`.

**`config.neon`**

```neon
extensions:
    dropdownlist: Phlox\Forms\DropDownList\DropDownListExtension
```

**Manual registration** (without DI):

```php
\Phlox\Forms\DropDownList\DropDownListExtension::register();
```

`addDropDownList()` signature:

```php
$form->addDropDownList(
    string $name,
    string $label    = '',
    string $searchUrl = '',
    string|array|null $theme = null,
): DropDownListInput
```

### DropDownListEndpoint

Optional dedicated AJAX component. Attach it to your presenter once and it handles search signals for **all** `DropDownListInput` controls on all forms on that presenter – no per-field wiring needed.

```php
use Phlox\Forms\DropDownList\DropDownListEndpoint;

protected function createComponentDropdownlist(): DropDownListEndpoint
{
    return new DropDownListEndpoint;
}
```

Signal URL pattern: `?do=dropdownlist-search&field=<controlName>&q=<query>`

If you prefer individual signal handlers (simpler for one or two controls), skip the endpoint and write a handler directly in the presenter:

```php
public function handleSearchCity(string $q = ''): void
{
    $this->sendJson($this['cityForm']['cityId']->fetchData($q));
}
```

---

## AJAX endpoint

The AJAX URL must return a JSON array of `{value, label}` objects:

```json
[
  { "value": 42, "label": "Prague" },
  { "value": 43, "label": "Brno" }
]
```

- `value` – the scalar that ends up in the hidden input and is submitted with the form (typically an integer PK)
- `label` – the string shown in the dropdown and in the trigger after selection

The widget sends:

```
GET /path/to/endpoint?q=pra
X-Requested-With: XMLHttpRequest
```

Requests are **debounced** (250 ms) and **aborted** if a newer keystroke arrives before the response.

---

## Themes

### Default theme

No setup required. Minimal framework-agnostic look with automatic dark mode.

```php
// No setTheme() call – default is applied automatically
$form->addDropDownList('cityId', 'City', $url)
     ->setSelection($sel, 'id', 'name');
```

### Bootstrap 5

```php
$form->addDropDownList('cityId', 'City', $url)
     ->setSelection($sel, 'id', 'name')
     ->setTheme(DropDownListInput::THEME_BOOTSTRAP);
```

Dark mode is automatic via Bootstrap 5.3+ `[data-bs-theme="dark"]` on `<html>` or any ancestor element. Bootstrap's own classes (`dropdown-menu`, `dropdown-item`, `form-control`, …) respond to this automatically. The widget's custom elements (filter row divider, filter input) are handled by scoped CSS rules in `dropdownlist.css`.

The Bootstrap trigger uses `form-select`, which provides its own chevron via CSS `background-image`. Our custom arrow element is automatically hidden via the `dropdownlist-bs-trigger` class so there is only one chevron visible.

### Tailwind CSS 3

```php
$form->addDropDownList('cityId', 'City', $url)
     ->setSelection($sel, 'id', 'name')
     ->setTheme(DropDownListInput::THEME_TAILWIND);
```

All classes include `dark:` variants. Requires Tailwind v3+ with the class-based dark mode strategy:

```js
// tailwind.config.js
module.exports = {
    darkMode: 'class',
    // …
}
```

Enable dark mode by adding the `dark` class to `<html>`.

### Custom theme

Override only the slots you need; the rest fall back to the defaults:

```php
$form->addDropDownList('cityId', 'City', $url)
     ->setTheme([
         'wrapper'      => 'my-wrapper',
         'trigger'      => 'my-select',
         'dropdown'     => 'my-dropdown',
         'filterRow'    => 'my-filter-row',
         'filterInput'  => 'my-filter-input',
         'item'         => 'my-item',
         'itemActive'   => 'my-item--active',
         'itemSelected' => 'my-item--selected',
         'noResults'    => 'my-no-results',
         'mark'         => 'my-match',
         'input'        => 'my-input',   // ComboBox mode only
     ]);
```

Multiple classes per slot are supported (space-separated):

```php
'trigger' => 'rounded border px-3 py-2 cursor-pointer',
```

> **Note:** The JS widget always attaches the panel via `data-dropdownlist-panel` and the filter input via `data-dropdownlist-filter` – these are stable data attributes independent of theme classes, so renaming CSS classes never breaks functionality.

---

## Label and focus behaviour

The trigger is rendered as `<button type="button">`. This means:

- A `<label for="id">` natively moves focus to the trigger without any JavaScript
- The browser's native Tab order works without explicit `tabindex`
- `Space` and `Enter` natively fire a click event on a focused button, toggling open/close

After opening, focus moves automatically to the **filter input** inside the dropdown, so the user can start typing immediately.

After picking an item or pressing `Escape`, focus returns to the trigger.


## Keyboard navigation

### Trigger (DropDownList mode)

| Key | Action |
|---|---|
| `Enter` / `Space` / `↓` | Open dropdown, focus filter input |
| `Escape` | Close dropdown |

### Filter input (inside open dropdown)

| Key | Action |
|---|---|
| `↓` | Move highlight to first / next item |
| `↑` | Move highlight to previous item; if at top, return focus to filter |
| `Enter` | Pick highlighted item and close |
| `Tab` | Pick highlighted item (if any) and close |
| `Escape` | Close without selecting, return focus to trigger |

### ComboBox mode (editable input)

| Key | Action |
|---|---|
| `↓` | Open dropdown / move highlight down |
| `↑` | Move highlight up |
| `Enter` | Pick highlighted item |
| `Tab` | Pick highlighted item (if any) and close |
| `Escape` | Restore previous value and close |

---

## Pre-filling values (edit pages)

When editing an existing record, call `setValue()` or `setValues()` on the form. The label for the current ID is resolved **server-side** from the `Selection` – no extra AJAX round-trip on page load:

```php
// In your edit action:
public function actionEdit(int $id): void
{
    $city = $this->cityRepository->getById($id);
    $this['editForm']->setValues(['cityId' => $city->id]);
}
```

Or directly on the control:

```php
$form['cityId']->setValue($city->id);
// $form['cityId']->getValue() now returns $city->id
```

If the value is not found in the Selection (e.g. soft-deleted record), `getValue()` still returns the raw value but the label is not shown.

---

## Advanced: custom filtering

Override `fetchData()` in a subclass for full-text search, joins, or multi-column matching:

```php
use Phlox\Forms\DropDownList\DropDownListInput;

class CityDropDownList extends DropDownListInput
{
    public function fetchData(string $query = ''): array
    {
        $sel = $this->getSelection();

        if ($query !== '') {
            $sel->where(
                'name LIKE ? OR zip_code LIKE ?',
                "%{$query}%",
                "%{$query}%",
            );
        }

        return array_map(
            fn($row) => ['value' => $row['id'], 'label' => "{$row['zip_code']} {$row['name']}"],
            $sel->limit(30)->fetchAll(),
        );
    }
}
```

Register it on the form container manually:

```php
$control = new CityDropDownList('City', $this->link('searchCity!'));
$control->setSelection($this->db->table('cities'), 'id', 'name');
$form->addComponent($control, 'cityId');
```

---

## Dark mode

**Default theme** – automatic via CSS `prefers-color-scheme: dark`. No configuration needed.

**Bootstrap 5.3+** – add `data-bs-theme="dark"` to any ancestor element (e.g. `<html>`):

```html
<html data-bs-theme="dark">
```

**Tailwind** – add the `dark` class to `<html>` (requires `darkMode: 'class'` in `tailwind.config.js`):

```html
<html class="dark">
```

---

## Naja / AJAX snippets

The widget automatically initialises controls injected into the DOM after page load. Both [Naja](https://naja.js.org/) (Nette's recommended AJAX library) and generic dynamic insertion are supported:

- **Naja**: the widget listens to `naja.addEventListener('complete', …)` and re-scans the document after every AJAX request
- **Generic dynamic insertion**: a `MutationObserver` watches `document.body` and initialises any newly added `[data-dropdownlist]` element

No extra configuration required.

---

## License

MIT © [Phlox](https://github.com/phloxcz)
