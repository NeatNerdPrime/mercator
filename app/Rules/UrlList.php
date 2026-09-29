<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UrlList implements ValidationRule
{
    /**
     * @param  string  $attribute  The attribute being validated
     * @param  mixed  $value  The current value of the attribute
     * @param  Closure  $fail  Closure to be run in case of failure
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (explode(',', $value) as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            // file:// allows links to network shares (file://server/share/...);
            // spaces are tolerated in paths, filter_var() only accepts them encoded
            if (! preg_match('/^(https?|file):\/\//i', $url)
                || filter_var(str_replace(' ', '%20', $url), FILTER_VALIDATE_URL) === false) {
                $fail($this->message());

                return;
            }
        }
    }

    public function message(): string
    {
        return 'Invalid URL List';
    }
}
