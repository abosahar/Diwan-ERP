<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every shortcut of the top-bar «إضافة» / «عرض» menus points at a real, permission-mapped route. */
class QuickMenusTest extends TestCase
{
    public function test_menu_routes_exist_and_are_mapped_to_permissions(): void
    {
        preg_match_all("/route: '([\\w.-]+)'/", file_get_contents(resource_path('js/quickMenus.js')), $m);
        $this->assertNotEmpty($m[1]);

        $map = config('erp_permissions.routes');
        foreach (array_unique($m[1]) as $name) {
            $this->assertTrue(Route::has($name), "Quick menu route [{$name}] does not exist.");
            $this->assertArrayHasKey($name, $map, "Quick menu route [{$name}] has no permission mapping (the menu hides it for everyone).");
        }
    }
}
