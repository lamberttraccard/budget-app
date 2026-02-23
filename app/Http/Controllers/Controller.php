<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function parseAccountIds(Request $request): ?array
    {
        $param = $request->query('accounts');

        if ($param === null || $param === '') {
            $param = $request->cookie('selectedAccounts');
        }

        if ($param === null || $param === '') {
            return null;
        }

        $ids = array_filter(array_map('intval', explode(',', $param)));

        return empty($ids) ? null : array_values($ids);
    }
}
