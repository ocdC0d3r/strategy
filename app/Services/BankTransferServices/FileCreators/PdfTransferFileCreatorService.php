<?php

namespace App\Services\BankTransferServices\FileCreators;

use App\Contracts\FileCreators\BankTransferFileInterface;

class PdfTransferFileCreatorService implements BankTransferFileInterface
{
    public function create()
    {
        return 'This is the Pdf file creator';
    }
}