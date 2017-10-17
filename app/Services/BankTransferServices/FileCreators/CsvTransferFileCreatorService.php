<?php

namespace App\Services\BankTransferServices\FileCreators;

use App\Contracts\FileCreators\BankTransferFileInterface;

class CsvTransferFileCreatorService implements BankTransferFileInterface
{
    public function create()
    {
        return 'This is the csv file creator';
    }
}