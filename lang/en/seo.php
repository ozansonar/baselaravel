<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO Auditor Texts
|--------------------------------------------------------------------------
|
| Every finding carries two sentences: the message states the problem, the
| hint states the fix. The split is deliberate — a warning that only names
| the problem leaves the author hunting through the screen.
|
| The tone describes rather than accuses: not "you got this wrong", but
| "this is missing".
|
*/

return [

    'levels' => [
        'error'   => 'Error',
        'warning' => 'Warning',
        'info'    => 'Suggestion',
    ],

    'panel' => [
        'title'   => 'SEO audit',
        'score'   => 'score',
        'run'     => 'Audit',
        'idle'    => 'Check the content before saving: missing meta, extra headings, images without alt text and broken links show up here.',
        'running' => 'Auditing…',
        'clean'   => 'No issues found for this language.',
        'failed'  => 'The audit did not run just now; it does not block saving, and you can try again in a moment.',
        'note'    => 'Findings are warnings; they never block saving.',
    ],

    'checks' => [

        'meta_title' => [
            'missing'        => 'The meta title is empty; search results will fall back to the page title.',
            'missing_hint'   => 'Write the title you want to appear in search results.',
            'duplicate'      => 'The meta title is an exact copy of the page title.',
            'duplicate_hint' => 'Leaving it empty would give the same result; make it distinct or clear the field.',
            'too_long'       => 'The meta title is :length characters; anything past :max is cut off in search results.',
            'too_long_hint'  => 'Move the most important words into the first :max characters.',
            'too_short'      => 'The meta title is :length characters; under :min it reads thin in results.',
            'too_short_hint' => 'Add a few words that say what the page is about.',
            'too_long_fallback'  => 'The page title used in search results is :length characters; anything past :max is cut off.',
            'too_short_fallback' => 'The page title used in search results is :length characters; under :min it reads thin.',
        ],

        'meta_description' => [
            'missing'        => 'The meta description is empty; the search engine will pick an arbitrary passage from the body.',
            'missing_hint'   => 'Summarise what the page covers in a sentence or two.',
            'too_long'       => 'The meta description is :length characters; anything past :max is not shown.',
            'too_long_hint'  => 'Fit the summary into :max characters.',
            'too_short'      => 'The meta description is :length characters; under :min it reads empty in results.',
            'too_short_hint' => 'Add one more sentence to round out the summary.',
        ],

        'heading' => [
            'extra_h1'      => 'The body contains :count H1 heading(s); the page title is already the H1.',
            'extra_h1_hint' => 'Change the headings in the body to H2 or lower.',
            'skipped'       => 'The heading order skips a level: :from jumps to :to.',
            'skipped_hint'  => 'Add the missing level or raise the heading one step.',
        ],

        'image_alt' => [
            'missing'      => ':count image(s) have no alt text.',
            'missing_hint' => 'Describe what the image shows; leave the alt text empty if it is decorative.',
        ],

        'cover_image' => [
            'missing'      => 'No cover image is set; shared links will have no preview.',
            'missing_hint' => 'Pick an image that represents the content.',
        ],

        'link_text' => [
            'generic'      => ':count link(s) do not say where they lead.',
            'generic_hint' => 'Replace the link text with the name of the target page.',
            'empty'        => ':count link(s) have no text.',
            'empty_hint'   => 'Add link text; if the link is an image, fill in its alt text.',
        ],

        'internal_link' => [
            'broken'      => ':count internal link(s) lead nowhere: :sample',
            'broken_hint' => 'Fix the address or remove the link.',
        ],

        'slug' => [
            'empty'         => 'The address is empty; it will be generated from the title on save.',
            'empty_hint'    => 'Fill the field in to see the address that will be used.',
            'too_long'      => 'The address is :length characters; under :max it is easier to share.',
            'too_long_hint' => 'Shorten the address; it does not have to carry the whole title.',
            'invalid'       => 'The address should contain only lowercase letters, digits and hyphens.',
            'invalid_hint'  => 'Spaces, capitals and accented characters break in addresses.',
            'mismatch'      => 'The address shares no word with the title.',
            'mismatch_hint' => 'If the title changed later, review the address too — but set up a redirect before changing a published one.',
        ],

        'content' => [
            'empty'      => 'The body is empty.',
            'empty_hint' => 'Write the page content.',
            'thin'       => 'The body is :words words; under :threshold counts as thin content.',
            'thin_hint'  => 'Cover the topic in more depth, or ignore this if the page is meant to be short.',
        ],

    ],

];
