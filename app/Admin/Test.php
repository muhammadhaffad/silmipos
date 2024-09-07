<?php
namespace App\Admin;

use Illuminate\Contracts\Support\Renderable;

class Test implements Renderable
{
    public function render() {
        return 'Hello';
    }
}