<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', ['isDraft' => $this->isDraft()]);
    }

    public function terms(): View
    {
        return view('legal.terms', ['isDraft' => $this->isDraft()]);
    }

    private function isDraft(): bool
    {
        return blank(config('legal.operator_name'))
            || blank(config('legal.governing_jurisdiction'))
            || blank(config('legal.hosting_providers'))
            || blank(config('legal.email_provider'))
            || blank(config('legal.effective_date'));
    }
}
