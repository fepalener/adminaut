<?php

namespace Adminaut\Manager;

use Adminaut\Entity\UserEntity;
use Adminaut\Entity\File as FileEntity;
use Adminaut\Entity\FileKeyword;
use Adminaut\Exception;
use Doctrine\ORM\EntityManager;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem;
use WideImage\WideImage;
use Zend\Form\ElementInterface;

/**
 * Class FileManager
 * @package Adminaut\Manager
 */
class FileManager extends AManager
{

    /**
     * @var FileEntity
     */
    private $file;

    /**
     * @var array
     */
    private $cache;

    /**
     * @var Filesystem
     */
    private $privateFilesystem;

    /**
     * @var Filesystem
     */
    private $publicFilesystem;

    /**
     * FileManager constructor.
     * @param EntityManager $entityManager
     * @param Filesystem $privateFilesystem
     * @param Filesystem $publicFilesystem
     */
    public function __construct(EntityManager $entityManager, Filesystem $privateFilesystem, Filesystem $publicFilesystem)
    {
        parent::__construct($entityManager);
        $this->privateFilesystem = $privateFilesystem;
        $this->publicFilesystem = $publicFilesystem;
    }

    /**
     * @return Filesystem
     */
    public function getPrivateFilesystem()
    {
        return $this->privateFilesystem;
    }

    /**
     * @return Filesystem
     */
    public function getPublicFilesystem()
    {
        return $this->publicFilesystem;
    }

    /**
     * @param $fileId
     * @return \Adminaut\Entity\File
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Adminaut\Exception\FileNotFoundException
     */
    public function getFileById($fileId)
    {
        if (isset($this->cache[$fileId])) {
            $entity = $this->cache[$fileId];
        } else {
            $entity = $this->getEntityManager()->find(FileEntity::class, $fileId);
        }
        if (!$entity) {
            throw new Exception\FileNotFoundException(
                'File does not exist.', 404
            );
        }
        $this->cache[$fileId] = $entity;
        return $entity;
    }

    /**
     * @param $keywords
     * @param bool|false $fromCache
     * @return mixed
     */
    /*public function getFilesByKeywords($keywords, $fromCache = false)
    {
        // Create unique ID of the array for cache
        $id = md5(serialize($keywords));

        // Change all given keywords to lowercase
        $keywords = array_map('strtolower', $keywords );

        // Get the entity from cache if available
        if ($fromCache && isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $list = "'" . implode("','", $keywords) . "'";

        $q = $this->em->createQuery(
            "select f from FileBank\Entity\File f, FileBank\Entity\Keyword k
             where k.file = f
             and k.value in (" . $list . ")"
            );

        // Cache the file entity so we don't have to access db on each call
        // Enables to get multiple entity's properties at different times
        $this->cache[$id] = $q->getResult();
        return $this->cache[$id];
    }*/

    /**
     * @param ElementInterface $element
     * @param UserEntity|null $user
     * @param array $option
     * @return FileEntity|null
     */
    public function upload(ElementInterface $element, UserEntity $user = null, array $option = [])
    {
        $_file = $element->getValue();
        if ($_file['error'] != 0) {
            return null;
        }
        $fileName = $_file['name'];
        $fileType = $_file['type'];
        $hash = md5(microtime(true) . $fileName);
        $savePath = substr($hash, 0, 1) . '/' . substr($hash, 1, 1) . '/';

        $file = new FileEntity();
        if ($user) {
            $file->setInsertedBy($user->getId());
        }
        if (isset($option['fileName'])) {
            $file->setName($option['fileName']);
        } else {
            $file->setName($fileName);
        }
        $file->setMimetype($fileType);
        $file->setSize($_file['size']);
        $file->setActive(true);
        $file->setSavePath($savePath . $hash);
        if (isset($option['keywords'])) {
            $this->addKeywordsToFile($option['keywords']);
        }

        try {
            $this->getPrivateFilesystem()->writeStream($savePath . $hash, fopen($_file['tmp_name'], 'r+'));

            if (method_exists($element, 'setFileObject')) {
                $element->setFileObject($file);
            }

            $this->getEntityManager()->persist($file);
            $this->getEntityManager()->flush($file); // flush file
        } catch (\Exception $e) {
            throw new Exception\RuntimeException(
                'File cannot be saved.', 0, $e
            );
        }

        return $file;
    }

    /**
     * @param FileEntity $file
     * @param int $width
     * @param int $height
     * @return mixed
     * @throws \Exception
     */
    public function getThumbImage(FileEntity $file, $width = 200, $height = 200)
    {
        $sourceImage = $file->getSavePath();
        $resultImage = $file->getSavePath() . '-' . $width . '-' . $height . '.' . $file->getFileExtension();

        /** @var LocalAdapter $fsAdapter */
        $fsAdapter = $this->getPrivateFilesystem()->getAdapter();

        if (!$this->getPublicFilesystem()->has($resultImage)) {
            try {
                $exif = exif_read_data($fsAdapter->applyPathPrefix($sourceImage));
                $_file = $this->getPrivateFilesystem()->read($sourceImage);
                $image = WideImage::load($_file);

                // Todo: Fork WideImage and add exifOrient operation!
                if (method_exists($image, 'exifOrient')) {
                    $ort = isset($exif['Orientation']) ? $exif['Orientation'] : 1;

                    $image_data = $image
                        ->exifOrient($ort)
                        ->resize($width, $height, 'outside')
                        ->crop('center', 'center', $width, $height)
                        ->asString($file->getFileExtension());
                } else {
                    $image_data = $image
                        ->resize($width, $height, 'outside')
                        ->crop('center', 'center', $width, $height)
                        ->asString($file->getFileExtension());
                }

                $this->getPublicFilesystem()->write($resultImage, $image_data);
            } catch (\Exception $e) {
                throw new \Exception(
                    'Thumbnail cannot be saved.', 0, $e
                );
            }
        }

//        /** @var LocalAdapter $fsAdapterCache */
//        $fsAdapterCache = $this->getPublicFilesystem()->getAdapter();

//        return $fsAdapterCache->applyPathPrefix($resultImage);

//        return str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', realpath($fsAdapterCache->getPathPrefix() . $resultImage)));


        // todo: make prefix configurable
        return '_cache/files/' . $resultImage;
    }

    /**
     * @param FileEntity $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @return mixed
     * @throws \Exception
     */
    public function getImage(FileEntity $file, $maxWidth = null, $maxHeight = null)
    {
        if (null === $maxWidth) {
            $maxWidth = 400;
        }

        if (null === $maxHeight) {
            $maxHeight = $maxWidth;
        }

        $sourceImage = $file->getSavePath();

        /** @var LocalAdapter $fsAdapter */
        $fsAdapter = $this->getPrivateFilesystem()->getAdapter();

        // todo: upraviť name súborov, podľa veľkosti originálu dopočítať novú veľkosť
        //$resultImage = $file->getSavePath() . '-' . $maxWidth . '-' . $maxHeight . '.' . $file->getFileExtension();
        $resultImage = $file->getSavePath() . '-' . 'resized' . '.' . $file->getFileExtension();

        if (!$this->getPublicFilesystem()->has($resultImage)) {
            try {
                $exif = exif_read_data($fsAdapter->applyPathPrefix($sourceImage));
                $_file = $this->getPrivateFilesystem()->read($sourceImage);
                $image = WideImage::load($_file);

                // Todo: Fork WideImage and add exifOrient operation!
                if (method_exists($image, 'exifOrient')) {
                    $ort = isset($exif['Orientation']) ? $exif['Orientation'] : 1;

                    $image_data = $image
                        ->exifOrient($ort)
                        ->resize($maxWidth, $maxHeight, 'inside', 'down')
                        ->asString($file->getFileExtension());
                } else {
                    $image_data = $image
                        ->resize($maxWidth, $maxHeight, 'inside', 'down')
                        ->asString($file->getFileExtension());
                }

                $this->getPublicFilesystem()->write($resultImage, $image_data);
            } catch (\Exception $e) {
                throw new \Exception(
                    'Resized original cannot be saved.', 0, $e
                );
            }
        }

//        /** @var LocalAdapter $fsAdapterCache */
//        $fsAdapterCache = $this->getPublicFilesystem()->getAdapter();

//        return $fsAdapterCache->applyPathPrefix($resultImage);

//        return str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', realpath($fsAdapterCache->getPathPrefix() . $resultImage)));


        // todo: make prefix configurable
        return '_cache/files/' . $resultImage;
    }

    /**
     * @param array $keywords
     * @return \Adminaut\Entity\File
     */
    protected function addKeywordsToFile(array $keywords)
    {
        if (!empty($keywords)) {
            $keywordEntities = [];
            foreach ($keywords as $word) {
                $keyword = new FileKeyword();
                $keyword->setValue(strtolower($word));
                $keyword->setFile($this->file);
                $this->getEntityManager()->persist($keyword);
                $keywordEntities[] = $keyword;
            }
            $this->file->setKeywords($keywordEntities);
        }
        return $this->file;
    }

    /**
     * @param $path
     * @param $mode
     * @param $isFileIncluded
     * @throws \Adminaut\Exception\RuntimeException
     */
    protected function createPath($path, $mode, $isFileIncluded)
    {
        $success = true;
        if (!is_dir(dirname($path))) {
            if ($isFileIncluded) {
                $success = mkdir(dirname($path), $mode, true);
            } else {
                $success = mkdir($path, $mode, true);
            }
        }
        if (!$success) {
            throw new Exception\RuntimeException('Can\'t create file manager storage folders');
        }
    }
}
