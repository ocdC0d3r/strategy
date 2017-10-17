<?php

namespace App\Http\Controllers;

use App\Services\BankTransferServices\FileCreators\{
    CsvTransferFileCreatorService,
    TxtTransferFileCreatorService,
    PdfTransferFileCreatorService
};
use App\Contracts\FileCreators\BankTransferFileInterface;
use App\Constants\TransferFileTypes;

class BankTransferController extends Controller
{
    /**
     * @var BankTransferFileInterface
     */
    private $bankTransferFile;

    /**
     * BankTransferController constructor.
     * @param BankTransferFileInterface $bankTransferFileType
     */
    private function setFileType(BankTransferFileInterface $bankTransferFileType)
    {
        $this->bankTransferFile = $bankTransferFileType;
    }

    /**
     * @return mixed
     * The method has only file creator logic
     * With the transfer logic it would be implemented somewhat different
     */
    public function process()
    {
        switch (request()->type) {
            case 'csv';
                $this->setFileType(new CsvTransferFileCreatorService);
                break;
            case 'txt';
                $this->setFileType(new TxtTransferFileCreatorService);
                break;
            case 'pdf';
                $this->setFileType(new PdfTransferFileCreatorService);
                break;
        }

        return $this->bankTransferFile->create();
    }
}