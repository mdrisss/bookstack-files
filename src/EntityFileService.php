<?php

namespace Mdrisss\BookstackFiles;

use BookStack\Entities\Models\Entity;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Exceptions\FileUploadException;
use BookStack\Exceptions\NotFoundException;
use BookStack\Uploads\FileStorage;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EntityFileService
{
    /**
     * Types d'entités auxquels on peut lier des fichiers (alias du morph-map BookStack).
     */
    public const SUPPORTED_TYPES = ['bookshelf', 'book', 'chapter', 'page'];

    public const DEFAULT_EXTENSIONS = [
        // Documents
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md',
        'xls', 'xlsx', 'ods', 'csv',
        'ppt', 'pptx', 'odp',
        // Schémas et configurations réseau
        'vsd', 'vsdx', 'drawio', 'svg', 'cfg', 'conf', 'log', 'json', 'xml', 'yaml', 'yml',
        // Images
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp',
        // Médias
        'mp4', 'webm', 'mp3', 'ogg', 'wav',
        // Archives
        'zip', '7z', 'tar', 'gz',
    ];

    public function __construct(
        protected FileStorage $storage,
        protected EntityQueries $entityQueries,
    ) {
    }

    /**
     * Retrouve une entité visible par l'utilisateur courant, ou échoue.
     *
     * @throws NotFoundException
     */
    public function findVisibleEntityOrFail(string $type, int $id): Entity
    {
        if (!in_array($type, self::SUPPORTED_TYPES)) {
            throw new NotFoundException(trans('bookstack-files::messages.entity_not_found'));
        }

        $entity = $this->entityQueries->findVisibleById($type, $id);
        if (!$entity) {
            throw new NotFoundException(trans('bookstack-files::messages.entity_not_found'));
        }

        return $entity;
    }

    /**
     * Retrouve un fichier dont l'entité parente est visible par l'utilisateur courant.
     * La visibilité d'un fichier est toujours celle de son entité.
     *
     * @return array{0: EntityFile, 1: Entity}
     * @throws NotFoundException
     */
    public function findVisibleFileOrFail(int $fileId): array
    {
        /** @var ?EntityFile $file */
        $file = EntityFile::query()->find($fileId);
        if (!$file) {
            throw new NotFoundException(trans('bookstack-files::messages.file_not_found'));
        }

        try {
            $entity = $this->findVisibleEntityOrFail($file->entity_type, $file->entity_id);
        } catch (NotFoundException) {
            throw new NotFoundException(trans('bookstack-files::messages.file_not_found'));
        }

        return [$file, $entity];
    }

    /**
     * @return Collection<int, EntityFile>
     */
    public function listForEntity(Entity $entity): Collection
    {
        return EntityFile::query()
            ->where('entity_type', '=', $entity->getMorphClass())
            ->where('entity_id', '=', $entity->id)
            ->with('createdBy')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @throws FileUploadException
     */
    public function saveUpload(UploadedFile $uploadedFile, Entity $entity): EntityFile
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $path = $this->storage->uploadFile(
            $uploadedFile,
            'uploads/files/bookstack-files/' . date('Y-m'),
            '',
            $extension,
        );

        $largestOrder = EntityFile::query()
            ->where('entity_type', '=', $entity->getMorphClass())
            ->where('entity_id', '=', $entity->id)
            ->max('order');

        $file = new EntityFile();
        $file->forceFill([
            'entity_type' => $entity->getMorphClass(),
            'entity_id'   => $entity->id,
            'name'        => mb_substr($uploadedFile->getClientOriginalName(), 0, 255),
            'path'        => $path,
            'extension'   => $extension,
            'size'        => $uploadedFile->getSize() ?: 0,
            'order'       => ($largestOrder ?? 0) + 1,
            'created_by'  => user()->id,
            'updated_by'  => user()->id,
        ])->save();

        return $file;
    }

    /**
     * @return resource|null
     */
    public function readStream(EntityFile $file)
    {
        return $this->storage->getReadStream($file->path);
    }

    public function getStoredSize(EntityFile $file): int
    {
        return $this->storage->getSize($file->path);
    }

    public function delete(EntityFile $file): void
    {
        $this->storage->delete($file->path, true);
        $file->delete();
    }

    public function deleteAllForEntity(Entity $entity): void
    {
        $files = EntityFile::query()
            ->where('entity_type', '=', $entity->getMorphClass())
            ->where('entity_id', '=', $entity->id)
            ->get();

        foreach ($files as $file) {
            $this->delete($file);
        }
    }

    /**
     * Extensions autorisées, surchargeables via BOOKSTACK_FILES_EXTENSIONS (liste séparée par des virgules).
     *
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        $configured = env('BOOKSTACK_FILES_EXTENSIONS');
        if (!$configured) {
            return self::DEFAULT_EXTENSIONS;
        }

        return array_values(array_filter(array_map(
            fn (string $ext) => strtolower(trim($ext, " .\t")),
            explode(',', $configured),
        )));
    }

    public function getValidationRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . (config('app.upload_limit') * 1000),
                'extensions:' . implode(',', $this->getAllowedExtensions()),
            ],
        ];
    }
}
