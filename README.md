# phloxcz/forms-dropdownlist

Searchable **DropDownList** form control for [Nette Framework](https://nette.org) with AJAX-powered `Nette\Database\Table\Selection` datasource.

📖 **[Full documentation →](docs/README.md)**

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

---

## Quick start

### 1. Register the DI extension

```neon
# config/common.neon
extensions:
    dropdownlist: Phlox\Forms\DropDownList\DropDownListExtension
```

### 2. Add the control to your form

```php
$form->addDropDownList('cityId', 'City', $this->link('searchCity!'))
     ->setSelection($this->database->table('cities'), 'id', 'name')
     ->setPlaceholder('Select city…')
     ->setTheme(DropDownListInput::THEME_BOOTSTRAP);
```

### 3. Handle the AJAX signal

```php
public function handleSearchCity(string $q = ''): void
{
    $this->sendJson($this['cityForm']['cityId']->fetchData($q));
}
```

### 4. Pre-fill on edit pages

```php
$form->setValues(['cityId' => $entity->cityId]);
// Label is resolved server-side – no extra AJAX on page load.
```

---

## Themes

| Constant | Framework |
|---|---|
| `DropDownListInput::THEME_DEFAULT` | Framework-agnostic, uses CSS system colors |
| `DropDownListInput::THEME_BOOTSTRAP` | Bootstrap 5 |
| `DropDownListInput::THEME_TAILWIND` | Tailwind CSS 3 |
| `array $classes` | Custom class map |

---

## License

MIT © [Phlox](https://github.com/phloxcz)
