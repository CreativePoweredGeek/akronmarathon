<?php

namespace BoldMinded\DataGrab\Service;

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\App;
use ExpressionEngine\Dependency\League\MimeTypeDetection\ExtensionMimeTypeDetector;
use ExpressionEngine\Dependency\League\MimeTypeDetection\FinfoMimeTypeDetector;
use ExpressionEngine\Model\File\UploadDestination;

class File
{
    private $baseUploadDestinationId;
    private $parentDirectoryId;

    public function __construct(
        private string $fileName,
        private int|float|string $fileDir = 1,
        private bool $fetchUrl = false,
        private bool $createSubDirs = false,
        private Logger|null $logger = null,
        private bool $replaceExisting = true,
        private FileMeta|null $fileMeta = null,
    ){}

    public function fetch(): FileResult
    {
        if (App::isLtEE7()) {
            return new FileResult(
                fileVar: $this->getFileLegacy()
            );
        }

        // Is it in the correct format already?
        if (preg_match('/{filedir_([0-9]+)}/', $this->fileName, $matches)) {
            return new FileResult(
                fileVar: $this->fileName
            );
        }

        // EE7+ Filemanager
        if (preg_match('/{file:([0-9]+):url}/', $this->fileName, $matches)) {
            return new FileResult(
                fileVar: $this->fileName
            );
        }

        // Is it a filename?
        if (preg_match('/http+/', $this->fileName, $matches) === false) {
            return new FileResult(
                fileVar: sprintf('{filedir_%s}%s', $this->fileDir, $this->fileName)
            );
        }

        // It's an external/full URL
        $url = parse_url($this->fileName);

        if (isset($url["scheme"]) && $this->fetchUrl === true) {
            try {
                ee('datagrab:UrlGuard')->assertAllowed($this->fileName);
            } catch (\Exception $exception) {
                $this->logger->log($exception->getMessage());
                return new FileResult(fileVar: '');
            }

            ee()->load->library('filemanager');
            ee()->load->library('upload');

            $basename = $this->getBaseName($this->fileName);

            try {
                $content = $this->safeRemoteRead($this->fileName);

                if ($content === false) {
                    return new FileResult(
                        fileVar: ''
                    );
                }
            } catch (\Exception $exception) {
                $this->logger->log(
                    sprintf(
                        '%s Try executing `curl %s` in your terminal to find out more information.',
                        $exception->getMessage(),
                        $this->fileName
                    )
                );

                return new FileResult(
                    fileVar: ''
                );
            }

            // Borrowed this little bit from EE core somewhere
            $dir_ids = explode('.', $this->fileDir);
            $upload_destination_id = (int) $dir_ids[0];
            $subdirectory_id = isset($dir_ids[1]) ? (int) $dir_ids[1] : 0;

            $uploadPrefs = ee()->filemanager->fetch_upload_dir_prefs($upload_destination_id, true);
            /** @var \ExpressionEngine\Library\Filesystem\Filesystem $fs */
            $fs = $uploadPrefs['directory']->getFilesystem();

            // For some reason this is needed
            if ($fs->isLocal()) {
                ee()->upload->upload_path = $uploadPrefs['server_path'];
            }

            $basename = $this->cleanFileName($basename);
            $fileId = $basename;
            $fileSystemEntityBasePath = '';

            // Create sub-directories recursively
            if ($url['path'] !== $basename && $this->createSubDirs) {
                $subDirs = array_filter(explode('/', $url['path']));
                array_pop($subDirs);

                if (!empty($subDirs)) {
                    $subDirPath = implode('/', $subDirs);
                    ee()->upload->upload_path .= $subDirPath;

                    // _baseServerPath and _subfolderPath in FileSystemEntity are private properties, so we're
                    // constructing the full file path instead of using getAbsolutePath() in the exists() call below.
                    $fileSystemEntityBasePath = $subDirPath .'/';

                    $subdirectory_id = $this->createSubDirectoriesRecursive($subDirPath, $upload_destination_id);
                }
            }

            /** @var \ExpressionEngine\Model\File\FileSystemEntity $file */
            $file = ee('Model')->make('FileSystemEntity');
            $fileData = [
                'upload_location_id' => $upload_destination_id,
                'directory_id' => $subdirectory_id,
                'file_name' => $basename,
            ];
            $file->set($fileData);

            $existsOnFileSystem = $fs->exists(
                $file->getBaseServerPath() .
                $fileSystemEntityBasePath .
                $file->file_name
            );

            $existsInFileManager = ee('Model')->get('File')
                ->filter('file_name', $basename)
                ->filter('upload_location_id', $upload_destination_id)
                ->filter('directory_id', $subdirectory_id)
                ->first();

            $saveResult = [];
            $saveFile = true;
            $isNew = false;

            ee()->upload->overwrite = $this->replaceExisting;
            ee()->upload->upload_destination = $uploadPrefs['directory'];

            // Apparently even setting this key if it's blank causes it not to work, so only set it if we have a value.
            // Also don't actually use the ee()->upload->initialize() method and pass in a clean array of arguments
            // b/c that too will mess this up. Apparently setting the properties directly is the only way.
            if ($file->getSubfoldersPath() !== '') {
                ee()->upload->upload_path = $file->getSubfoldersPath();
            }

            if($existsOnFileSystem && $existsInFileManager && !$this->replaceExisting) {
                $saveResult = [
                    'file_id' => $existsInFileManager->getId()
                ];
                $saveFile = false;
                $this->logger->log(
                    sprintf(
                        '%s already exists on the file system, and in the File Manager, no changes necessary.',
                        $basename
                    )
                );
            } elseif ($existsOnFileSystem && !$existsInFileManager && !$this->replaceExisting) {
                $fileData['file_size'] = $fs->getSize($file->getAbsolutePath());
                $this->logger->log(
                    sprintf(
                        '%s already exists on the file system, but not in the File Manager.',
                        $basename
                    )
                );
            } else {
                $rawUploadResult = null;

                try {
                    $fileId = $existsInFileManager?->getId() ?: 0;
                    $filePath = $file->getAbsolutePath();
                    $existsOnFileSystem = $uploadPrefs['directory']->getFilesystem()->exists($filePath);
                    $isNew = true;

                    if ($existsOnFileSystem && $this->replaceExisting) {
                        $uploadPrefs['directory']->getFilesystem()->delete($filePath);

                        // It's already in the FM so we don't need to create a new record, thus a new file_id
                        if ($existsInFileManager) {
                            $saveFile = false;
                            $isNew = false;
                        }

                        $this->logger->log(sprintf(
                            'Attempting raw file upload of %s to replace the existing version.',
                            $basename
                        ));
                        $rawUploadResult = ee()->upload->raw_upload($basename, $content);

                        if ($rawUploadResult) {
                            $this->logger->log(sprintf('Raw file upload of %s was a success.', $basename));
                        } else {
                            $this->logger->log(sprintf('Raw file upload of %s failed.', $basename));
                        }
                    }

                    if ($existsOnFileSystem && $existsInFileManager && !$this->replaceExisting) {
                        $this->logger->log(sprintf(
                            '%s already exists on the file system, skipping upload.',
                            $basename
                        ));
                        $saveFile = false;
                    }

                    if (!$existsOnFileSystem) {
                        $this->logger->log(sprintf('Attempting raw file upload of %s.', $basename));
                        $rawUploadResult = ee()->upload->raw_upload($basename, $content);

                        if ($rawUploadResult) {
                            $this->logger->log(sprintf('Raw file upload of %s was a success.', $basename));
                        } else {
                            $this->logger->log(sprintf('Raw file upload of %s failed.', $basename));
                        }
                    }
                } catch (\Exception $exception) {
                    $saveFile = false;
                    $this->logger->log($exception->getMessage());
                }

                if (!$existsOnFileSystem && $existsInFileManager && $rawUploadResult) {
                    $fileData['file_id'] = $existsInFileManager->getId();

                    $this->logger->log(
                        sprintf(
                            '%s does not exist on the file system, but exists File Manager.',
                            $basename
                        )
                    );
                } elseif (!$existsOnFileSystem && !$existsInFileManager && $rawUploadResult) {
                    $this->logger->log(
                        sprintf(
                            '%s does not exist on the file system or in the File Manager.',
                            $basename
                        )
                    );
                }

                // ee()->upload->file_size is available, but it's converted to kb, need original bytes here
                $fileData['file_size'] = filesize(ee()->upload->file_temp);
            }

            if ($saveFile) {
                try {
                    $this->logger->log(sprintf(
                        'Attempting to save %s.',
                        $file->getAbsolutePath()
                    ));

                    ee()->load->library('filemanager');
                    $saveResult = ee()->filemanager->save_file(
                        $file->getAbsolutePath(),
                        $upload_destination_id,
                        $fileData,
                        false
                    );
                } catch (\Exception $exception) {
                    $this->logger->log(sprintf(
                        'Save failed for %s: %s',
                        $file->getAbsolutePath(),
                        $exception->getMessage()
                    ));
                }

                if ($saveResult && isset($saveResult['file_id'])) {
                    $this->logger->log(sprintf(
                        '%s saved to the File Manager.',
                        $file->getAbsolutePath()
                    ));

                    if ($this->fileMeta) {
                        $this->saveFileMeta($saveResult['file_id']);
                    }
                }
            }

            // Use the new EE7 format if possible
            if ($saveResult && isset($saveResult['file_id']) && !bool_config_item('file_manager_compatibility_mode')) {
                return new FileResult(
                    fileVar: sprintf('{file:%d:url}', $saveResult['file_id']),
                    filePath: $file->getAbsolutePath(),
                    isNew: $isNew,
                );
            }

            if ($fileId && is_numeric($fileId) && !bool_config_item('file_manager_compatibility_mode')) {
                return new FileResult(
                    fileVar: sprintf('{file:%d:url}', $fileId),
                    filePath: $file->getAbsolutePath(),
                    isNew: $isNew,
                );
            }

            return new FileResult(
                fileVar: sprintf('{filedir_%s}%s', $upload_destination_id, $fileId),
                filePath: $file->getAbsolutePath(),
                isNew: $isNew,
            );

        }

        return new FileResult();
    }

    private function saveFileMeta(int $fileId): void
    {
        $fileModel = ee('Model')->get('File')
            ->filter('file_id', $fileId)
            ->first();

        if (!$fileModel) {
            return;
        }

        $this->logger->log(sprintf(
            'Saving file meta for %s',
            $fileModel->title
        ));

        $hasChanges = false;

        $currentDescription = $fileModel->description ?? '';
        $currentCredit = $fileModel->credit ?? '';
        $currentLocation = $fileModel->location ?? '';

        $newDescription = $this->fileMeta->description ?? '';
        $newCredit = $this->fileMeta->credit ?? '';
        $newLocation = $this->fileMeta->location ?? '';

        if ($currentDescription !== $newDescription) {
            $fileModel->description = $newDescription;
            $hasChanges = true;
        }

        if ($currentCredit !== $newCredit) {
            $fileModel->credit = $newCredit;
            $hasChanges = true;
        }

        if ($currentLocation !== $newLocation) {
            $fileModel->location = $newLocation;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $fileModel->save();
        }
    }

    public static function getFileIdFromFileVar(string $fileVar): int
    {
        if (preg_match('/{file:([0-9]+):url}/', $fileVar, $matches)) {
            return $matches[1];
        } else if (preg_match('/{filedir_([0-9]+)}/', $fileVar, $matches)) {
            $file = ee('Model')->get('File')
                ->filter('upload_location_id', $matches[1])
                ->filter('directory_id', 0)
                ->filter('file_name', preg_replace('/{filedir_([0-9]+)}/', '', $fileVar))
                ->first();

            return $file->getId();
        }

        return 0;
    }

    public static function getFileUploadLocationFromFileVar(string $fileVar): UploadDestination|null
    {
        if (preg_match('/{filedir_([0-9]+)}/', $fileVar, $matches)) {
            return ee('Model')->get('UploadDestination', $matches[1])->first();
        }

        $fileId = self::getFileIdFromFileVar($fileVar);

        /** @var \ExpressionEngine\Model\File\File $file */
        $file = ee('Model')->get('File')
            ->filter('file_id', $fileId)
            ->filter('model_type', 'File')
            ->first();

        if (!$file) {
            return null;
        }

        return $file->UploadDestination;
    }

    public function createSubDirectoriesRecursive(string $fullPath, int $baseUploadDestinationId): int {
        $folders = array_values(array_filter(explode('/', $fullPath)));

        $this->baseUploadDestinationId = $baseUploadDestinationId;
        $this->parentDirectoryId = 0;

        foreach ($folders as $folder) {
            $this->createSubDirectory($folder);
        }

        return $this->parentDirectoryId;
    }

    private function createSubDirectory(string $directoryName)
    {
        $uploadDirectory = ee('Model')->get('UploadDestination', $this->baseUploadDestinationId)->first();

//        if (!ee('Permission')->can('upload_new_files') ||
//            !$uploadDirectory->memberHasAccess(ee()->session->getMember()) ||
//            bool_config_item('file_manager_compatibility_mode') || !$uploadDirectory->allow_subfolders
//        ) {
//            show_error(lang('unauthorized_access'), 403);
//        }

        if ($this->parentDirectoryId !== 0) {
            $directory = ee('Model')->get('Directory', $this->parentDirectoryId)
                ->filter('upload_location_id', $this->baseUploadDestinationId)
                ->filter('model_type', 'Directory')
                ->first();

            if (empty($directory)) {
                $this->logger->log('Can\'t find parent directory id %d ' . $this->parentDirectoryId);
            }

            $filesystem = $directory->getFilesystem();
        } else {
            $filesystem = $uploadDirectory->getFilesystem();
        }

        $existing = ee('Model')->get('Directory')
            ->filter('directory_id', $this->parentDirectoryId)
            ->filter('upload_location_id', $this->baseUploadDestinationId)
            ->filter('model_type', 'Directory')
            ->filter('title', $directoryName)
            ->first();

        if ($existing) {
            $this->parentDirectoryId = $existing->file_id;
            return;
        }

        $subdir = ee('Model')->make('Directory');
        $subdir->file_name = $directoryName;
        $subdir->upload_location_id = $this->baseUploadDestinationId;
        $subdir->directory_id = $this->parentDirectoryId;
        $subdir->site_id = $uploadDirectory->site_id;

        $validation = $subdir->validate();

        if (!$validation->isValid()) {
            $this->logger->log('Invalid subdirectory creation attempt: %s ' . implode(' ', $validation->failed()));
            return;
        }

        // Directory does not exist, so attempt to create it
        $created = $filesystem->mkDir($directoryName);

        if (!$created) {
            $this->logger->log('Can\'t create directory %s ' . $directoryName);
        }

        if ($subdir->save()) {
            $this->parentDirectoryId = $subdir->file_id;
        } else {
            $this->logger->log('Can\'t save directory %s ' . $directoryName);
        }
    }

    /**
     * The raw_upload function fails if the file has more than 1 . in the name, e.g. some.file.jpg
     * Borrowed from Upload->_prep_filename(), except we're not doing any mimetype checks here.
     *
     * @param string $fileName
     * @return string
     */
    private function cleanFileName(string $fileName): string
    {
        if (strpos($fileName, '.') === false) {
            return $fileName;
        }

        $parts = explode('.', $fileName);
        $ext = array_pop($parts);
        $fileName = array_shift($parts);

        if (bool_config_item('datagrab_clean_filenames')) {
            $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName);
        }

        if (bool_config_item('datagrab_use_short_filenames')) {
            return $fileName . '.' . $ext;
        }

        foreach ($parts as $part) {
            $fileName .= '_' . $part;
        }

        $fileName .= '.' . $ext;

        return $fileName;
    }

    private function getBaseName(string $fileName): string
    {
        $basename = basename($fileName);
        $url = parse_url($fileName);

        if (strpos($basename, "?")) {
            $basename = substr($basename, 0, strpos($basename, "?"));
        }

        // If the file path has a query parameter on it, assume it's a script that is returning
        // a file. In this case we check the mime type, make sure it's safe, and try to get the
        // real file extension. Note this will slow down imports as it'll remotely load the first
        // 50 bytes of a file to check its mimetype before attempting to actually import it.
        if (isset($url['query'])) {
            $basename = $this->getFileNameParts($basename)[0] ?? $basename;
            // Make it unique
            $basename .= '-' . ee()->security->sanitize_filename($url['query']);
            $mimeType = $this->getMimeType($fileName);

            // Then hopefully add an extension
            if (in_array($mimeType, ee('MimeType')->getWhitelist())) {
                $basename .= '.' . $this->getExtensionFromMimeType($mimeType);
            }
        }

        return $basename;
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimes = $this->loadMimes();

        foreach ($mimes as $extension => $types) {
            if (is_array($types) && in_array($mimeType, $types)) {
                return $extension;
            }

            if ($mimeType === $extension) {
                return $extension;
            }
        }

        return '';
    }

    private function getFileNameParts(string $fileName): array
    {
        $pos = strrpos($fileName, '.');

        if ($pos === false) {
            return [$fileName];
        }

        return [
            substr($fileName, 0, $pos),
            substr($fileName, $pos + 1)
        ];
    }

    private function loadMimes(): array
    {
        $mimes = include PATH_THIRD . 'datagrab/config/mimes.php';

        return $mimes;
    }

    /**
     * Read a remote URL while keeping the SSRF guard intact across redirects.
     *
     * PHP's HTTP wrapper would otherwise follow 3xx responses to an arbitrary
     * (possibly internal) host that UrlGuard never saw. We disable automatic
     * redirect following and resolve any redirect manually, re-validating each
     * hop with UrlGuard — mirroring AbstractDataType::curlFetch. The first URL is
     * assumed to have already been validated by the caller.
     *
     * @return string|false
     */
    private function safeRemoteRead(string $url, ?int $maxLen = null)
    {
        $followRedirects = (bool) ee()->config->item('datagrab_follow_redirects');
        $maxRedirects = $followRedirects ? (int) (ee()->config->item('datagrab_max_redirects') ?: 5) : 0;

        $context = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'max_redirects' => 1,
                'ignore_errors' => true,
            ],
        ]);

        for ($hop = 0; ; $hop++) {
            // PHP leaves a prior request's $http_response_header in place when a
            // fetch fails at the transport layer, so clear it before each hop.
            unset($http_response_header);

            $content = $maxLen !== null
                ? file_get_contents($url, false, $context, 0, $maxLen)
                : file_get_contents($url, false, $context);

            // A failed fetch is terminal; never re-follow stale headers.
            if ($content === false) {
                return false;
            }

            $location = $this->redirectLocation($http_response_header ?? []);

            if ($location === null || $hop >= $maxRedirects) {
                return $content;
            }

            // Resolve relative redirects against the current URL, then re-validate.
            $url = $this->resolveRedirectUrl($url, $location);

            try {
                ee('datagrab:UrlGuard')->assertAllowed($url);
            } catch (\Exception $exception) {
                $this->logger->log($exception->getMessage());
                return false;
            }
        }
    }

    private function redirectLocation(array $headers): ?string
    {
        $isRedirect = false;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(3\d\d)\b#', $header)) {
                $isRedirect = true;
            }
            if ($isRedirect && stripos($header, 'Location:') === 0) {
                return trim(substr($header, strlen('Location:')));
            }
        }

        return null;
    }

    private function resolveRedirectUrl(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $location);
        }

        $path = $parts['path'] ?? '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1);

        return sprintf('%s://%s%s%s%s', $scheme, $host, $port, $dir, $location);
    }

    /**
     * This is a copy of ee('MimeType')->ofFile($path), but we can't call it directly
     * because it immediately throws an exception if the file does not already exist
     * locally. We need to get the mime (and thus extension) of a remote file before importing it.
     *
     * @param string $fileName
     * @return string
     */
    private function getMimeType(string $fileName): string
    {
        $file_opening = $this->safeRemoteRead($fileName, 50); //get first 50 bytes off the file
        $detector = new FinfoMimeTypeDetector();
        $mime = $detector->detectMimeType($fileName, $file_opening);

        // A few files are identified as plain text, which while true is not as
        // helpful as which type of plain text files they are.
        if ($mime == 'text/plain') {
            $detectorByExtension = new ExtensionMimeTypeDetector();
            $mimeByExtension = $detectorByExtension->detectMimeTypeFromFile($fileName);
            if (!empty($mimeByExtension)) {
                $mime = $mimeByExtension;
            }
        }

        // Set a default
        $mime = !is_null($mime) ? $mime :  'application/octet-stream';

        // try another method to get mime
        if ($mime == 'application/octet-stream') {
            $file_opening = ($file_opening) ?: $this->safeRemoteRead($fileName, 50);
            $mime = ee('MimeType')->guessOctetStream($file_opening);
        }

        return $mime;
    }

    /**
     * @return string
     */
    private function getFileLegacy(): string
    {
        // Is it in the correct format already?
        if (preg_match('/{filedir_([0-9]+)}/', $this->fileName, $matches)) {
            return $this->fileName;
        }

        // Is it a filename?
        if (!preg_match('/http+/', $this->fileName, $matches)) {
            return "{filedir_" . $this->fileDir . "}" . $this->fileName;
        }

        // It's an external/full URL
        $url = parse_url($this->fileName);

        if (isset($url["scheme"])) {
            ee()->load->library('filemanager');
            ee()->filemanager->xss_clean_off();

            $basename = basename($this->fileName);
            if (strpos($basename, "?")) {
                $basename = substr($basename, 0, strpos($basename, "?"));
            }
            $basetitle = $basename;

            $file_path = ee()->filemanager->clean_filename(
                $basename,
                $this->fileDir,
                array('ignore_dupes' => true)
            );

            if (file_exists($file_path)) {
                return '{filedir_' . $this->fileDir . '}' . $basename;
            }

            if ($this->fetchUrl === true) {
                if (!isset($content)) {
                    $content = @file_get_contents($this->fileName);
                }
                if ($content === false) {
                    return '';
                }

                if (file_put_contents($file_path, $content) === false) {
                    $this->logger('Can\'t copy file to ' . $file_path);
                    return '';
                }

                $result = ee()->filemanager->save_file(
                    $file_path,
                    $this->fileDir,
                    array(
                        'title' => $basetitle,
                        'path' => dirname($file_path),
                        'file_name' => $basename
                    )
                );

                if ($result['status'] === false) {
                    return '';
                }

                return '{filedir_' . $this->fileDir . '}' . $basename;
            }

        }

        return '';
    }

    /**
     * @param string $url
     * @return array
     */
    private function getRemoteFileDetails(string $url): array
    {
        $uh = curl_init();
        curl_setopt($uh, CURLOPT_URL, $url);

        // set NO-BODY to not receive body part
        curl_setopt($uh, CURLOPT_NOBODY, 1);

        // set HEADER to be false, we don't need header
        curl_setopt($uh, CURLOPT_HEADER, 0);

        // retrieve last modification time
        curl_setopt($uh, CURLOPT_FILETIME, 1);
        curl_exec($uh);

        // assign filesize into $filesize variable
        $filesize = curl_getinfo($uh, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        // assign file modification time into $filetime variable
        $filetime = curl_getinfo($uh, CURLINFO_FILETIME);
        curl_close($uh);

        return [
            'size' => $filesize,
            'time' => $filetime,
        ];
    }
}
