<?php

use Illuminate\Http\RedirectResponse;

if (!function_exists('redirectBack')) {

    /**
     * Create a RedirectResponse which can optionally contain a fragment/anchor that links to a tab on the page we
     * will go back to. If the fragment is not specified or is null, then this is equivalent to redirect()->back().
     *
     * @param string|null $fragment Fragment or null
     * @return RedirectResponse Redirection
     */
    function redirectBack(?string $fragment = null): RedirectResponse
    {
        $previousAnchor = request()->input("__anchor");
        if ($fragment === null && $previousAnchor !== null) {
            $fragment = $previousAnchor;
        }
        return $fragment ? redirect()->to(url()->previous() . '#' . $fragment) : redirect()->back();
    }
}