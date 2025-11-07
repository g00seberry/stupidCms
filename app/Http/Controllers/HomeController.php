<?php

namespace App\Http\Controllers;

use App\Domain\Options\OptionsRepository;
use App\Models\Entry;

final class HomeController
{
    public function __invoke(OptionsRepository $repository)
    {
        $id = $repository->getInt('site', 'home_entry_id', null);
        if ($id) {
            $entry = Entry::published()->find($id);
            if ($entry) {
                // Рендер через общий Page renderer
                return view('pages.show', ['entry' => $entry]);
            }
        }
        return view('home.default');
    }
}

