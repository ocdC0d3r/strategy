<?php

namespace App\Services\BankTransferServices\FileCreators;

use App\Contracts\FileCreators\BankTransferFileInterface;

class TxtTransferFileCreatorService implements BankTransferFileInterface
{
    public function create()
    {
        return 'This is the Txt file creator';
    }
}