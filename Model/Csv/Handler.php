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

    const HEADERS = [
        'programid',
        'type',
        'partnerid',
        'userip',
        'ordreid',
        'varenummer',
        'antal',
        'omprsalg'
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
     * @return string
     */
    protected function getFilePath()
    {
        return rtrim(
            self::DIRECTORY_NAME,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . ltrim(
                self::FILE_NAME,
                DIRECTORY_SEPARATOR
            );
    }

    /**
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

        if (is_array($headers) && $this->validateHeaders($headers)) {
            return $file;
        }

        $file->writeCsv(self::HEADERS);
        $file->unlock();
        return $file;
    }

    /**
     * @param array $lineData
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function addLine(array $lineData)
    {
        if (true !== $this->validateHeaders(array_keys($lineData))) {
            return false;
        }

        $file = $this->initFile();
        $file->lock();
        $bool = (false !== $file->writeCsv(array_values($lineData)));
        $file->unlock();
        return $bool;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getDownloadFile()
    {
        $read = $this->getReadVarDir();

        if (true !== $read->isExist($this->getFilePath())) {
            return false;
        }

        $data = $read->readFile($this->getFilePath());

        if (empty($data)) {
            return false;
        }

        return $this->fileFactory->create(self::FILE_NAME, $data);
    }
}
