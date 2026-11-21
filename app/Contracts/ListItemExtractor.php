<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ListItemExtractor
{
    /** @return list<string> Proposed item names for review before saving. */
    public function fromText(string $text): array;

    /** @return list<string> Proposed item names for review before saving. */
    public function fromHandwriting(UploadedFile $image): array;
}
