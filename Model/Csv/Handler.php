<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Csv;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Partner\Module\Api\CsvHandlerInterface;
use Magento\Framework\App\Response\Http\FileFactory;

class Handler implements CsvHandlerInterface
{
    const FILE_NAME = 'failed_export.csv';

    const DIRECTORY_NAME = 'partner';

    /**
     * The export data fields (as sent to Partner-ads), then why and when the export failed (UTC)
     */
    const HEADERS = [
        'programid',
        'type',
        'partnerid',
        'pacid',
        'ordreid',
        'varenummer',
        'antal',
        'omprsalg',
        'reason',
        'failed_at'
    ];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * Handler constructor.
     * @param Filesystem $filesystem
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Filesystem $filesystem,
        FileFactory $fileFactory
    ) {
        $this->filesystem = $filesystem;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @return Filesystem\Directory\WriteInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getWriteVarDir()
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * @return Filesystem\Directory\ReadInterface
     */
    protected function getReadVarDir()
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
    }

    /**
     * @param array $headers
     * @return bool
     */
    protected function validateHeaders(array $headers)
    {
        return (array_values(self::HEADERS) === array_values($headers));
    }

    /**
     * Path relative to var/
     *
     * @return string
     */
    protected function getFilePath()
    {
        return self::DIRECTORY_NAME . '/' . self::FILE_NAME;
    }

    /**
     * Opens the file for appending, locked, with the header line written if it is new
     *
     * @return Filesystem\File\WriteInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function initFile()
    {
        $write = $this->getWriteVarDir();

        if (!$write->isExist(self::DIRECTORY_NAME)) {
            $write->create(self::DIRECTORY_NAME);
        }

        $file = $write->openFile($this->getFilePath(), 'a+');
        $file->lock();
        $file->seek(0);
        $headers = $file->readCsv();

        if (!is_array($headers) || !$this->validateHeaders($headers)) {
            $file->writeCsv(self::HEADERS);
        }

        return $file;
    }

    /**
     * @param array $lineData every key of HEADERS (other keys are ignored)
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function addLine(array $lineData)
    {
        $line = [];
        foreach (self::HEADERS as $column) {
            if (!array_key_exists($column, $lineData)) {
                return false;
            }
            $line[] = $lineData[$column];
        }

        $file = $this->initFile();
        try {
            $bool = (false !== $file->writeCsv($line));
        } finally {
            $file->unlock();
            $file->close();
        }
        return $bool;
    }

    /**
     * Streams var/partner/failed_export.csv. Nothing is written elsewhere and the file is kept.
     *
     * @return \Magento\Framework\App\ResponseInterface|bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getDownloadFile()
    {
        $read = $this->getReadVarDir();
        $path = $this->getFilePath();

        if (true !== $read->isFile($path)) {
            return false;
        }

        $stat = $read->stat($path);
        if (empty($stat['size'])) {
            return false;
        }

        return $this->fileFactory->create(
            self::FILE_NAME,
            ['type' => 'filename', 'value' => $path, 'rm' => false],
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
