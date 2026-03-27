<?php

declare(strict_types=1);

namespace Phlox\Forms\DropDownList;

use Nette\DI\CompilerExtension;
use Nette\Forms\Container;
use Nette\PhpGenerator\ClassType;

/**
 * Nette DI extension.
 *
 * Registers `addDropDownList()` shortcut on every Nette\Forms\Container.
 *
 * Registration in config.neon
 * ---------------------------
 *   extensions:
 *       dropdownlist: Phlox\Forms\DropDownList\DropDownListExtension
 *
 * Without DI (e.g. in Bootstrap.php):
 *   \Phlox\Forms\DropDownList\DropDownListExtension::register();
 */
class DropDownListExtension extends CompilerExtension
{
    public function afterCompile(ClassType $class): void
    {
        $class->getMethod('initialize')
            ->addBody('\Phlox\Forms\DropDownList\DropDownListExtension::register();');
    }

    /**
     * Register addDropDownList() on every form Container.
     * Safe to call multiple times (extensionMethod is idempotent).
     *
     * @param string|array<string,string>|null $theme  THEME_* constant, custom class map, or null for default.
     */
    public static function register(): void
    {
        Container::extensionMethod(
            'addDropDownList',
            static function (
                Container $container,
                string $name,
                string $label = '',
                string $searchUrl = '',
                string|array|null $theme = null,
            ): DropDownListInput {
                $control = new DropDownListInput($label, $searchUrl);
                if ($theme !== null) {
                    $control->setTheme($theme);
                }
                $container->addComponent($control, $name);
                return $control;
            }
        );
    }
}
