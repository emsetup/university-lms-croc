<?php

namespace App\Http\Controllers;

use App\Support\AdminNavigation;

abstract class Controller
{
    /**
     * @return array<string, string>
     */
    protected function adminCourseRouteParams(): array
    {
        return AdminNavigation::adminCourseRouteParams();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function adminCourseRoute(string $name, array $parameters = []): string
    {
        return route($name, array_merge($this->adminCourseRouteParams(), $parameters));
    }
}
