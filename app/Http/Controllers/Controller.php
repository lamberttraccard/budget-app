<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ParsesAccountIds;

abstract class Controller
{
    use ParsesAccountIds;
}
